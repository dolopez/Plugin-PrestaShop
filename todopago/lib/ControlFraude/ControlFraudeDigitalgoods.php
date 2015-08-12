<?php

require_once(dirname(__FILE__)."/ControlFraude.php");
require_once(dirname(__FILE__)."/../../classes/Productos.php");

class ControlFraudeDigitalgoods extends ControlFraude {

	protected function completeCSVertical(){
		$productos = $this->datasources["cart"]->getProducts();
		$controlFraude = new TPProductoControlFraude($productos[0]['id_product']);
		
		$datosCS["CSMDD32"] = $controlFraude->tipo_delivery;
		return array_merge($this->getMultipleProductsInfo(), $datosCS);
	}

	protected function getCategoryArray($id_product){
		$controlFraude = new TPProductoCybersource($id_product);
        return $controlFraude->codigo_producto;
	}
}