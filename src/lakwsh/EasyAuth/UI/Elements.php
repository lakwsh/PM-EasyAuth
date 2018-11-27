<?php
namespace lakwsh\EasyAuth;

abstract class UIElement implements \JsonSerializable{
	protected $text='';
	final public function __construct(string $text=''){
		$this->text=$text;
		return;
	}
	abstract public function jsonSerialize():array;
}
class Button extends UIElement{
	private $imageType='';
	private $imagePath='';
	public function setImage(string $imageType,string $imagePath):bool{
		if($imageType!=='path' and $imageType!=='url') return false;
		$this->imageType=$imageType;
		$this->imagePath=$imagePath;
		return true;
	}
	final public function jsonSerialize():array{
		$data=array('type'=>'button','text'=>$this->text);
		if($this->imageType!=='') $data['image']=array('type'=>$this->imageType,'data'=>$this->imagePath);
		return $data;
	}
}
class DropDown extends UIElement{
	private $options=array();
	private $defaultOptionIndex=0;
	public function setOptions(array $options){
		$this->options=$options;
		return;
	}
	public function setDefault($value):bool{
		$index=array_search($value,$this->options);
		if($index===false) return false;
		$this->defaultOptionIndex=$index;
		return true;
	}
	final public function jsonSerialize():array{
		return array('type'=>'dropdown','text'=>$this->text,'options'=>$this->options,'default'=>$this->defaultOptionIndex);
	}
}
class Input extends UIElement{
	private $placeholder='';
	private $defaultText='';
	public function setPlaceholder(string $value){
		$this->placeholder=$value;
		return;
	}
	public function setDefault(string $value){
		$this->defaultText=$value;
		return;
	}
	final public function jsonSerialize():array{
		return array('type'=>'input','text'=>$this->text,'placeholder'=>$this->placeholder,'default'=>$this->defaultText);
	}
}
class Label extends UIElement{
	final public function jsonSerialize():array{
		return array('type'=>'label','text'=>$this->text);
	}
}
class Slider extends UIElement{
	private $min=0;
	private $max=0;
	private $step=0;
	private $defaultValue=0;
	public function setRange(float $min,float $max):bool{
		if($min>$max) return false;
		$this->min=$min;
		$this->max=$max;
		return true;
	}
	public function setStep(float $value):bool{
		if($value<0) return false;
		$this->step=$value;
		return true;
	}
	public function setDefault(float $value):bool{
		if($value<$this->min or $value>$this->max) return false;
		$this->defaultValue=$value;
		return true;
	}
	final public function jsonSerialize():array{
		$data=array('type'=>'slider','text'=>$this->text,'min'=>$this->min,'max'=>$this->max);
		if($this->step>0) $data['step']=$this->step;
		if($this->defaultValue!==$this->min) $data['default']=$this->defaultValue;
		return $data;
	}
}
class StepSlider extends UIElement{
	private $steps=array();
	private $defaultStepIndex=0;
	public function setSteps(array $steps){
		$this->steps=$steps;
		return;
	}
	public function setDefault($value):bool{
		$index=array_search($value,$this->steps);
		if($index===false) return false;
		$this->defaultStepIndex=$index;
		return true;
	}
	final public function jsonSerialize():array{
		return ['type'=>'step_slider','text'=>$this->text,'steps'=>$this->steps,'default'=>$this->defaultStepIndex];
	}
}
class Toggle extends UIElement{
	private $defaultValue=false;
	public function setDefaultValue(bool $value){
		$this->defaultValue=$value;
		return;
	}
	public function jsonSerialize():array{
		return array('type'=>'toggle','text'=>$this->text,'default'=>$this->defaultValue);
	}
}