<?php

/**
 * Class Container
 * @package ${NAMESPACE}
 */

class Container
{
    public $binding = [];


    /**
     * @param $abstract
     * @param null $concrete
     * @param bool $shared
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->binding[$abstract] = compact('concrete', 'shared');
    }



    protected function getClosure($abstract, $concrete)
    {
        return function ($c) use ($abstract, $concrete) {
            $method = ($abstract == $concrete) ? 'build' : 'make';

            return $c->$method($concrete);
        };
    }

    /**
     * @param $abstract
     * @return object
     *
     */
    public function make($abstract)
    {
        $concrete = $this->getConcrete($abstract);

        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete);
        } else {
            $object = $this->make($concrete);
        }
        return $object;
    }

    /**
     * @param $concrete
     * @param $abstract
     * @return bool
     */
    public function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * @param $abstract
     * @return mixed
     */
    protected function getConcrete($abstract)
    {
        if (!isset($this->binding[$abstract])) {
            return $abstract;
        }
        return $this->binding[$abstract]['concrete'];
    }


    /**
     * @param $concrete
     * @return object
     */
    public function build($concrete) {

        if($concrete instanceof Closure) {

            return $concrete($this);
        }

        //反射...
        $reflector = new ReflectionClass($concrete);
        if(!$reflector->isInstantiable()) {
            echo $message = "Target [$concrete] is not instantiable";
        }
        //获取要实例化对象的构造函数
        $constructor = $reflector->getConstructor();

        //没有定义构造函数,只有默认的构造函数,说明构造函数参数个数为空
        if(is_null($constructor)) {
            return new $concrete;
        }

        //获取构造函数所需要的所有参数
        $dependencies = $constructor->getParameters();
        $instances = $this->getDependencies($dependencies);

        //从给出的数组参数在中实例化对象
        return $reflector->newInstanceArgs($instances);
    }

    /**
     * @param $paramters
     * @return array
     * 获取构建类所需要的所有依赖,级构造函数所需要的参数 ,
     */
    protected function getDependencies($paramters) {
        $dependencies = [];

        foreach ($paramters as $paramter) {
            //获取到参数名称.
            $dep = $paramter->getClass();
            if(is_null($dep)){
                $dependencies = null;
            }else{
                $dependencies[] = $this->resolveClass($paramter);
            }
        }
        return (array)$dependencies;
    }

    /**
     * @param ReflectionParameter $parameter
     * @return object
     * 实例化 构造函数中所需要的参数.
     */
    protected function resolveClass(ReflectionParameter $parameter) {
        $name = $parameter->getClass()->name;
        return $this->make($name);
    }

}



interface TrafficTool
{
    public function go();
}

class Train implements TrafficTool
{

    public function go()
    {
        echo "train....";
    }
}

class Leg implements TrafficTool
{
    public function go()
    {
        echo "leg..";
    }
}

class Traveller
{
    /**
     * @var Leg|null|Train
     * 旅行工具
     */
    protected $_trafficTool;

    public function __construct(TrafficTool $trafficTool)
    {
        $this->_trafficTool = $trafficTool;
    }

    public function visitTibet()
    {
        $this->_trafficTool->go();
    }
}

//实例化IoC容器
$app = new Container();

//绑定某一功能到IoC
$app->bind('TrafficTool', 'Train');
$app->bind('travellerA', 'Traveller');

// 实例化对象
$tra = $app->make('travellerA');
$tra->visitTibet();