<?php
namespace app\index\controller;

use think\Controller;

class Wisdom extends Controller {
    
    
    public function index() {
        
        return $this->fetch();
    }
    
    public function home() {
        
        return $this->fetch();
    }
    
}