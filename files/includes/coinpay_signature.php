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
        //throw_if(isset($params['signature']), new \Exception("Can't sign! Already signed!"));
        //$data["signature"] = $this->generate($data);
        //return $data;
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
        $data = array_walk_recursive($data, function($item) {
          if (is_array($item)) {
            return ksort($item);
          }
          return $item;
        });
        $signable_string = json_encode($data);
        return sha1($signable_string);
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
