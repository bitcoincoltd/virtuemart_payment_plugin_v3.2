<?php

class CoinpaySignature
{
    protected $secret;

    public function __construct($secret)
    {
        $this->secret = $secret;
    }

    /**
     * @param array $data
     * @return array mixed
     * @throws \Exception
     */
    public function sign($data)
    {
      throw new Exception("Not implemented yet!");
    }

    /**
     * Generate signature
     * @param array $data
     * @return string
     */
    public function generate($data)
    {
        $data['secret'] = $this->secret;
        // sort to have always the same order in signature.
        $this->ksortRecursive($data);

        $signable_string = json_encode($data);
        return sha1($signable_string);
    }

    public function ksortRecursive(&$array)
    {
      if (!is_array($array)) return;
      ksort($array);
      foreach ($array as &$arr) {
        $this->ksortRecursive($arr);
      }
    }

    public function check($data)
    {
        if (!isset($data['signature'])) {
            return false;
        }

        $signature        = $data['signature'];
        $correctSignature = $this->generate($this->clean($data));

        return $signature === $correctSignature;
    }

    protected function clean($data)
    {
        unset($data['signature']);
        return $data;
    }
}
