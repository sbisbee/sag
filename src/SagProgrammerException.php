<?php
class SagProgrammerException extends Exception
{
  public function SagProgrammerException($msg = "", $code = 0)
  {
    parent::__construct("Sag Error: $msg", $code);
  }
}
?>
