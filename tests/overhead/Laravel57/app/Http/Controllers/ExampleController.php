<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use DDTrace\GlobalTracer;

class ExampleController extends BaseController
{
    public function example()
    {
        $tracer = GlobalTracer::get();
        $span = $tracer->getActiveSpan();
        // $span->setTag('my.log.tag.one', $this->generateRandomString(2000));
        // $span->setTag('my.log.tag.two', $this->generateRandomString(2000));
        // $span->setTag('my.log.tag.three', $this->generateRandomString(2000));
        // $span->setTag('my.log.tag.four', $this->generateRandomString(2000));
        error_log('This is the example action');
        return "hi!";
    }

    function generateRandomString($length = 1000) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
