<?php
class Router {
    private array $routes = [];
    public function get(string $path, $h){$this->add('GET',$path,$h);} 
    public function post(string $path, $h){$this->add('POST',$path,$h);} 
    private function add(string $m,string $path,$h){$pat='#^'.preg_replace('/\{([a-zA-Z0-9_]+)\}/','(?P<$1>[^/]+)',$path).'$#';$this->routes[]=['m'=>$m,'p'=>$pat,'h'=>$h];}
    public function run(){
        $m=$_SERVER['REQUEST_METHOD']??'GET';
        $p=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
        if($p!=='/' && str_ends_with($p,'/')) $p=rtrim($p,'/');
        foreach($this->routes as $r){
            if($r['m']===$m && preg_match($r['p'],$p,$match)){
                $params=array_filter($match,'is_string',ARRAY_FILTER_USE_KEY);
                return $this->dispatch($r['h'],$params);
            }
        }
        http_response_code(404); echo '404';
    }
    private function dispatch($h,$params){
        if(is_callable($h)) return call_user_func_array($h,$params);
        if(is_string($h)){
            $parts=explode('@',$h);$ctrl=$parts[0];$method=$parts[1]??'index';
            $file=__DIR__."/Controllers/{$ctrl}.php";if(!file_exists($file)) die('Controller not found');
            require_once $file; $obj=new $ctrl(); if(!method_exists($obj,$method)) die('Method not found');
            // Smart param passing: if controller method has 1 param and it's not array-typed, pass first value only
            $ref=new ReflectionMethod($obj,$method); $num=$ref->getNumberOfParameters();
            if($num===0) return call_user_func([$obj,$method]);
            if($num===1){ $p=$ref->getParameters()[0]; $t=$p->getType();
                if($t && $t->getName()==='array') return call_user_func([$obj,$method],$params);
                return call_user_func([$obj,$method], array_values($params)[0] ?? null);
            }
            return call_user_func_array([$obj,$method], array_values($params));
        }
    }
}
