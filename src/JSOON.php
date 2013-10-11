<?php
/**
The MIT License (MIT)

Copyright (c) 2013 Benjamin Young (aka BigBlueHat)

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
**/

/**
 * json_decode($json) in PHP gives you Object-style notation (returning a stdClass
 * of the JSON).
 * 
 * json_decode($json, true) in PHP gives you Array-style notation (returning a
 * nested array of the JSON).
 * 
 * Some days, I want both.
 * 
 * Meet JSOON.
 **/

class JSOON implements ArrayAccess
{
  public $data = null;

  public function __construct($json) {
    if (is_string($json)) {
      $this->data = json_decode($json);
    } else if (is_object($json)) {
      $this->data = $json;
    }
  }

  public function __toString() {
    return json_encode($this->data);
  }

  public function __sleep() {
    return $this->data;
  }

  public function __get($name) {
    return $this->data->$name;
  }

  public function __set($name, $value) {
    $this->data->$name = $value;
  }

  public function offsetExists($offset) {
    return isset($this->data->$offset);
  }

  public function offsetSet($offset, $value) {
    $this->data->$offset = $value;
  }

  public function offsetGet($offset) {
    if (is_object($this->data->$offset)) {
      return new JSOON($this->data->$offset);
    } else {
      return $this->data->$offset;
    }
  }

  public function offsetUnset($offset) {
    unset($this->data->$offset);
  }
}
