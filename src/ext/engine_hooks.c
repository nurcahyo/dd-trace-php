#include "engine_hooks.h"

#include <Zend/zend_exceptions.h>
#include <Zend/zend_vm.h>
#include <php.h>
#include <stdint.h>
#include <time.h>

#include "ddtrace.h"
#include "dispatch.h"

// todo: purge dependency?
#include "dispatch.h"
#include "dispatch_compat.h"
#include "logging.h"
#include "span.h"
#include "trace.h"

ZEND_EXTERN_MODULE_GLOBALS(ddtrace);

// True gloals that need not worry about thread safety
#if PHP_VERSION_ID >= 70000
static user_opcode_handler_t _prev_ucall_handler;
#endif
static user_opcode_handler_t _prev_fcall_handler;
static user_opcode_handler_t _prev_fcall_by_name_handler;

BOOL_T _is_traceable_execute_data(zend_execute_data *execute_data) {
    if (DDTRACE_G(disable) || DDTRACE_G(disable_in_current_request) || DDTRACE_G(class_lookup) == NULL ||
        DDTRACE_G(function_lookup) == NULL || !execute_data) {
        return FALSE;
    }
    zend_function *fbc = EX(func);
    if (!fbc) {
        return FALSE;
    }

    if (!fbc->common.function_name) {
        return FALSE;
    }

    // Don't trace closures
    if (fbc->common.fn_flags & ZEND_ACC_CLOSURE) {
        return FALSE;
    }
    return TRUE;
}

static zval *_fetch_this(zend_execute_data *execute_data) {
    if (execute_data && Z_TYPE(EX(This)) == IS_OBJECT) {
        return &EX(This);
    }
    return NULL;
}

ddtrace_dispatch_t *_try_get_dispatch(zend_execute_data *execute_data) {
    if (_is_traceable_execute_data(execute_data)) {
        zend_function *fbc = EX(func);
        zval *this = _fetch_this(execute_data);
        zval fname;
        ZVAL_STR(&fname, fbc->common.function_name);
        ddtrace_dispatch_t *dispatch = ddtrace_find_dispatch(this, fbc, &fname);
        return dispatch;
    }
    return NULL;
}

static void _begin_tracing(zend_execute_data *ex TSRMLS_DC) {
    ddtrace_dispatch_t *dispatch = _try_get_dispatch(ex);
    if (dispatch && !dispatch->busy) {
        ddtrace_open_span(ex, dispatch TSRMLS_CC);
    }
}

static int ddtrace_fcall_handler(zend_execute_data *execute_data TSRMLS_DC) {
    if (EX(call) && EX(call)->func && EX(call)->func->type != ZEND_INTERNAL_FUNCTION) {
        _begin_tracing(EX(call));
    }
    return ZEND_USER_OPCODE_DISPATCH;
}

static int ddtrace_fcall_by_name_handler(zend_execute_data *execute_data TSRMLS_DC) {
    return ddtrace_fcall_handler(execute_data TSRMLS_CC);
}

static zval *ddtrace_get_zval_ptr(int op_type, const znode_op *node, zend_execute_data *zdata,
                                  zend_free_op *should_free TSRMLS_DC) {
#if PHP_VERSION_ID < 70300
    return zend_get_zval_ptr(op_type, node, zdata, should_free, BP_VAR_RW TSRMLS_CC);
#else
    return zend_get_zval_ptr(zdata->opline, op_type, node, zdata, should_free, BP_VAR_RW);
#endif
}

static void _end_tracing(zend_execute_data *execute_data, zval *retval TSRMLS_DC) {
    ddtrace_span_t *span = DDTRACE_G(open_spans_top);
    if (span && execute_data == span->execute_data) {
        ddtrace_end_span_with_tracing_closure(span->dispatch, span->execute_data, retval, span TSRMLS_CC);
    }
}

static int ddtrace_return_handler(zend_execute_data *execute_data) {
    const zend_op *opline = EX(opline);
    zend_free_op should_free;
    zval *retval = ddtrace_get_zval_ptr(opline->op1_type, &opline->op1, execute_data, &should_free TSRMLS_CC);
    _end_tracing(execute_data, retval);
    return ZEND_USER_OPCODE_DISPATCH;
}

static int ddtrace_handle_exception_handler(zend_execute_data *execute_data) {
    ddtrace_span_t *span = DDTRACE_G(open_spans_top);
    if (span && span->execute_data == execute_data) {
        zval retval;
        ZVAL_NULL(&retval);
        _end_tracing(execute_data, &retval);
    }
    return ZEND_USER_OPCODE_DISPATCH;
}

/* It's possible to not use zend_execute_internal:
 *   1. Override the function handler. This doesn't mesh with how our integrations are loaded today.
 *   2. Re-implement the ICALL handler. I'm not comfortable enough to do this; maybe with more experience we could.
 * The impact of using zend_execute_internal is that all ICALLs are routed through FCALLs instead. As far as I can tell
 * this is slower but otherwise equivalent.
 */
static void (*dispatch_execute_internal)(zend_execute_data *, zval *);
static void (*previous_execute_internal)(zend_execute_data *, zval *);
static void ddtrace_execute_internal(zend_execute_data *execute_data, zval *retval TSRMLS_DC) {
    _begin_tracing(execute_data TSRMLS_CC);
    dispatch_execute_internal(execute_data, retval TSRMLS_CC);
    _end_tracing(execute_data, retval TSRMLS_CC);
}

static zend_op_array *(*_prev_compile_file)(zend_file_handle *file_handle, int type TSRMLS_DC);

static void _opcode_minit(void);
static void _opcode_mshutdown(void);
static void _compile_minit(void);
static void _compile_mshutdown(void);

void ddtrace_engine_hooks_minit(void) {
    _opcode_minit();
    _compile_minit();
}

void ddtrace_engine_hooks_mshutdown(void) {
    _compile_mshutdown();
    _opcode_mshutdown();
}

void ddtrace_engine_hooks_rinit(void) {
    // todo: figure out why this is required for our ZEND_HANDLE_EXCEPTION to trigger
    ZEND_VM_SET_OPCODE_HANDLER(EG(exception_op));
    EG(exception_op)->opcode = ZEND_HANDLE_EXCEPTION;
}

static void _opcode_minit(void) {
#if PHP_VERSION_ID >= 70000
    _prev_ucall_handler = zend_get_user_opcode_handler(ZEND_DO_UCALL);
    zend_set_user_opcode_handler(ZEND_DO_UCALL, ddtrace_fcall_handler);
#endif

    _prev_fcall_handler = zend_get_user_opcode_handler(ZEND_DO_FCALL);
    _prev_fcall_by_name_handler = zend_get_user_opcode_handler(ZEND_DO_FCALL_BY_NAME);
    zend_set_user_opcode_handler(ZEND_DO_FCALL, ddtrace_fcall_handler);
    zend_set_user_opcode_handler(ZEND_DO_FCALL_BY_NAME, ddtrace_fcall_by_name_handler);

    zend_set_user_opcode_handler(ZEND_RETURN, ddtrace_return_handler);
    zend_set_user_opcode_handler(ZEND_HANDLE_EXCEPTION, ddtrace_handle_exception_handler);
}

static void _opcode_mshutdown(void) {
#if PHP_VERSION_ID >= 70000
    zend_set_user_opcode_handler(ZEND_DO_UCALL, NULL);
#endif
    zend_set_user_opcode_handler(ZEND_DO_FCALL, NULL);
    zend_set_user_opcode_handler(ZEND_DO_FCALL_BY_NAME, NULL);
}

int ddtrace_opcode_default_dispatch(zend_execute_data *execute_data TSRMLS_DC) {
    if (!EX(opline)->opcode) {
        return ZEND_USER_OPCODE_DISPATCH;
    }
    switch (EX(opline)->opcode) {
#if PHP_VERSION_ID >= 70000

        case ZEND_DO_UCALL:
            if (_prev_ucall_handler) {
                return _prev_ucall_handler(execute_data TSRMLS_CC);
            }
            break;
#endif
        case ZEND_DO_FCALL:
            if (_prev_fcall_handler) {
                return _prev_fcall_handler(execute_data TSRMLS_CC);
            }
            break;

        case ZEND_DO_FCALL_BY_NAME:
            if (_prev_fcall_by_name_handler) {
                return _prev_fcall_by_name_handler(execute_data TSRMLS_CC);
            }
            break;
    }
    return ZEND_USER_OPCODE_DISPATCH;
}

static uint64_t _get_microseconds() {
    struct timespec time;
    if (clock_gettime(CLOCK_MONOTONIC, &time) == 0) {
        return time.tv_sec * 1000000U + time.tv_nsec / 1000U;
    }
    return 0U;
}

static zend_op_array *_dd_compile_file(zend_file_handle *file_handle, int type TSRMLS_DC) {
    zend_op_array *res;
    uint64_t start = _get_microseconds();
    res = _prev_compile_file(file_handle, type TSRMLS_CC);
    DDTRACE_G(compile_time_microseconds) += (int64_t)(_get_microseconds() - start);
    return res;
}

static void _compile_minit(void) {
    _prev_compile_file = zend_compile_file;
    zend_compile_file = _dd_compile_file;

    previous_execute_internal = zend_execute_internal;
    dispatch_execute_internal = zend_execute_internal ? zend_execute_internal : execute_internal;
    zend_execute_internal = ddtrace_execute_internal;
}

static void _compile_mshutdown(void) {
    if (zend_compile_file == _dd_compile_file) {
        zend_compile_file = _prev_compile_file;
    }
    zend_execute_internal = previous_execute_internal;
}

void ddtrace_compile_time_reset(TSRMLS_D) { DDTRACE_G(compile_time_microseconds) = 0; }

int64_t ddtrace_compile_time_get(TSRMLS_D) { return DDTRACE_G(compile_time_microseconds); }
