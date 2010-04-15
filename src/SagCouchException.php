<?php
class SagCouchException extends Exception
{
  public function SagCouchException($msg = "", $code = 0)
  {
    parent::__construct("CouchDB Error: $msg", $code);
  }
}
?>
