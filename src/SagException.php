<?php
class SagException extends Exception
{
  public function SagException($msg = "", $code = 0)
  {
    parent::__construct("Sag Error: $msg", $code);
  }
}
?>
