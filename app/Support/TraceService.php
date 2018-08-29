<?php


namespace App\Support;

use whitemerry\phpkin\Tracer;
use whitemerry\phpkin\Endpoint;
use whitemerry\phpkin\Span;
use whitemerry\phpkin\Identifier\SpanIdentifier;
use whitemerry\phpkin\AnnotationBlock;
use whitemerry\phpkin\Logger\SimpleHttpLogger;
use whitemerry\phpkin\TracerInfo;

class TraceService {
    public $tracer;
    public $serverName;
    public $zipkinHost;
    public $port;
    public $ip;
    public $status;
    public function __construct()
    {
        $this->serverName = env('APP_NAME');
        $this->zipkinHost = env('ZIPKINHOST');
        $this->port = app('request')->getPort();
        $this->status = env('TRACESTATUS');
//        $this->ip = $_SERVER['SERVER_ADDR'];
        $this->ip = '127.0.0.1';

    }

    public function makeTrace()
    {
        if($this->traceStatus()){
            $log  = new SimpleHttpLogger(['host' => $this->zipkinHost, 'muteErrors' => true]);
            $endpoint = new Endpoint($this->serverName, $this->ip, $this->port);
            $this->tracer = new Tracer($this->serverName, $endpoint, $log);
        }else{
            return false;
        }

    }

    public function endTrace()
    {
        if($this->traceStatus()){
            $this->tracer->trace();
        }
        return false;
    }

    public function traceStatus()
    {
        if($this->status == 'true'){
            return true;
        }
        return false;
    }

    public function addSpan()
    {
        if($this->traceStatus()){
            $time = zipkin_timestamp();
            $spanId = new SpanIdentifier();
            return [$time,$spanId];
        }else{
            return ['',''];
        }

    }

    public function addSpanEnd($time,$spanId,$action)
    {
        if($this->traceStatus()){
            $endpoint = new Endpoint($this->serverName, $this->ip, $this->port);
            $span = new Span($spanId, $action, new AnnotationBlock($endpoint, $time));
            $this->tracer->addSpan($span);
        }else{
            return false;
        }
    }

    public function headerAdd()
    {
        $spanId = new SpanIdentifier();

        return [
            'X-B3-TraceId'=>(string)TracerInfo::getTraceId(),
            'X-B3-ParentSpanId'=>(string)TracerInfo::getTraceSpanId(),
            'X-B3-SpanId'=>(string)$spanId,
            'X-B3-Sampled'=>true
        ];
    }
}