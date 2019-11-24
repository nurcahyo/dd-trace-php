#ifndef DDTRACE_TRACE_H
#define DDTRACE_TRACE_H
#include <php.h>

#include "dispatch.h"
#include "env_config.h"
#include "span.h"

void ddtrace_trace_dispatch(ddtrace_dispatch_t *dispatch, zend_function *fbc,
                            zend_execute_data *execute_data TSRMLS_DC);
BOOL_T ddtrace_tracer_is_limited(TSRMLS_D);

void ddtrace_end_span_with_tracing_closure(ddtrace_dispatch_t *dispatch, zend_execute_data *execute_data, zval *retval,
                                           ddtrace_span_t *span TSRMLS_DC);
#endif  // DDTRACE_TRACE_H
