<?php

namespace Espo\Core;

use \Espo\Core\Utils\Util;
use \Espo\Core\Exceptions\NotFound;

class ControllerManager
{
	private $config;

	private $metadata;
	
	private $container;

	public function __construct(\Espo\Core\Container $container)
	{	
		$this->container = $container;
		
		$this->config = $this->container->get('config');
		$this->metadata = $this->container->get('metadata');
	}

    protected function getConfig()
	{
		return $this->config;
	}

	protected function getMetadata()
	{
		return $this->metadata;
	}
	
	public function process($controllerName, $actionName, $params, $data, $request)
	{		
		$customeClassName = '\\Espo\\Custom\\Controllers\\' . Util::normilizeClassName($controllerName);
		if (class_exists($customeClassName)) {
			$controllerClassName = $customeClassName;
		} else {
			$moduleName = $this->metadata->getScopeModuleName($controllerName);
			if ($moduleName) {
				$controllerClassName = '\\Espo\\Modules\\' . $moduleName . '\\Controllers\\' . Util::normilizeClassName($controllerName);
			} else {
				$controllerClassName = '\\Espo\\Controllers\\' . Util::normilizeClassName($controllerName);
			}
		}		
			
		if ($data) {
			$data = json_decode($data);
		}

		
		if ($data instanceof \stdClass) {
			$data = get_object_vars($data);
		}

		if (!class_exists($controllerClassName)) {
			throw new NotFound("Controller '$controllerName' is not found");
		}		

		$controller = new $controllerClassName($this->container);

		if ($actionName == 'index') {
			$actionName = $controllerClassName::$defaultAction;
		}		
		
		$actionNameUcfirst = ucfirst($actionName);
		
		$beforeMethodName = 'before' . $actionNameUcfirst;			 
		if (method_exists($controller, $beforeMethodName)) {
			$controller->$beforeMethodName($params, $data, $request);
		}
		$actionMethodName = 'action' . $actionNameUcfirst;
		
		if (!method_exists($controller, $actionMethodName)) {			
			throw new NotFound("Action '$actionMethodName' does not exist in controller '$controller'");
		}	

		$result = $controller->$actionMethodName($params, $data, $request);
		
		$afterMethodName = 'after' . $actionNameUcfirst;	
		if (method_exists($controller, $afterMethodName)) {
			$controller->$afterMethodName($params, $data, $request);
		}

		if (is_array($result) || is_bool($result)) {
        	return \Espo\Core\Utils\Json::encode($result);
		}
		
		return $result;
	}

}
