<?php
namespace lakwsh\EasyAuth;

class SimpleForm implements \JsonSerializable{
	private $title='';
	private $content='';
	private $buttons=[];
	// SimpleForm only consists of clickable buttons
	public function __construct(string $title,string $content=''){
		$this->title=$title;
		$this->content=$content;
		return;
	}
	public function setButtons(array $buttons):bool{
		foreach($buttons as $button){if(!$button instanceof UIElement){return false;}}
		$this->buttons=$buttons;
		return true;
	}
	final public function jsonSerialize():array{
		$data=array('type'=>'form','title'=>$this->title,'content'=>$this->content,'buttons'=>[]);
		foreach($this->buttons as $button) $data['buttons'][]=$button;
		return $data;
	}
}
class ModalWindow implements \JsonSerializable{
	private $title='';
	private $content='';
	private $trueButtonText='';
	private $falseButtonText='';
	// This is a window to show a simple text to the player
	public function __construct(string $title,string $content,string $trueButtonText,string $falseButtonText){
		$this->title=$title;
		$this->content=$content;
		$this->trueButtonText=$trueButtonText;
		$this->falseButtonText=$falseButtonText;
		return;
	}
	final public function jsonSerialize():array{
		return array('type'=>'modal','title'=>$this->title,'content'=>$this->content,'button1'=>$this->trueButtonText,'button2'=>$this->falseButtonText);
	}
}
class CustomForm implements \JsonSerializable{
	private $title='';
	private $elements=[];
	private $iconURL='';
	// CustomForm is a totally custom and dynamic form
	public function __construct(string $title){
		$this->title=$title;
		return;
	}
	public function setElements(array $elements):bool{
		foreach($elements as $element){if(!$element instanceof UIElement){return false;}}
		$this->elements=$elements;
		return true;
	}
	// Only for server settings
	public function setIconUrl(string $url){
		$this->iconURL=$url;
		return;
	}
	final public function jsonSerialize():array{
		$data=array('type'=>'custom_form','title'=>$this->title,'content'=>[]);
		if($this->iconURL!='') $data['icon']=array('type'=>'url','data'=>$this->iconURL);
		foreach($this->elements as $element) $data['content'][]=$element;
		return $data;
	}
}