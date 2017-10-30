<?php

namespace MarioFlores\VessleImages;

use MarioFlores\Proxynpm\Proxynpm;
use MarioFlores\Equasis\Database;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use Exception;

class VessleImages {

    public $response;
    private $client;
    public $output = false;
    public $vessle;
    public $errors = array();
    public $path;
    private $sucess;
    public $path_proxy;

    public function setGuzzle() {
        if (empty($this->path_proxy)) {
            throw new Exception("Porxy path is empty");
        }
        $proxys = new Proxynpm($this->path_proxy);
        $proxys->output = $this->output;
        $proxy = $proxys->getProxy();
        $this->setHeaders();
        $this->client = new Client([
            'headers' => $this->setHeaders(),
            'timeout' => 120,
            'cookies' => new \GuzzleHttp\Cookie\CookieJar,
            'http_errors' => false,
            'allow_redirects' => true,
            'proxy' => 'tcp://' . $proxy['ip'] . ':' . $proxy['port']
        ]);
    }

    private function setHeaders() {
        return [
            'User-Agent' => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0",
            'Accept-Language' => "en-US,en;q=0.5"
        ];
    }

    function saveFile($vessle) {
        try {
            $this->response = $this->client->request('GET', 'http://photos.marinetraffic.com/ais/showphoto.aspx?mmsi=' . $vessle['mmsi'] . '&size=');
            $this->output($this->response->getHeader('Content-Type'));
            if ($this->response->getHeader('Content-Type') == 'image/jpeg') {
                $name = 'b_' . $vessle['id'] . '.jpg';
                $saveto = $this->path . $name;
                $file = fopen($saveto, 'w');
                fwrite($file, $response->getBody());
                fclose($file);
                $this->sucess = true;
                $this->vessle['barco_foto'] = $name;
            } else {
                $this->errors[] = "content type of fetch image problem";
                $this->sucess = false;
            }
        } catch (Exception $e) {
            $this->sucess = false;
            $this->errors[] = "content type of fetch image problem";
            $this->errors[] = $e->getMessage();
        }
    }

    function getImage($vessle) {
        try {
            if (empty($this->path)) {
                throw new Exception("path to save files is empty");
            }
            $this->setGuzzle();
            $this->output($vessle['mmsi']);
            $this->sucess = $this->saveFile($vessle);
            if ($this->sucess) {
                $this->output($this->vessle['barco_foto']);
                $this->otherData($vessle);
            }
        } catch (Exception $ex) {
            $this->errors[] = $ex->getMessage();
        }
        $this->output(implode("\n", $this->errors));
    }

    function otherData($vessle) {
        try {
            $response = $this->client->request('GET', 'http://www.marinetraffic.com/en/ais/details/ships/mmsi:' . $vessle['mmsi']);

            $html = new Crawler($response->getBody()->getContents());

            $size = $html->filter('.group-ib > b')->eq(7)->text();

            $size = explode(' ', $size);
            if (!empty($size[2])) {

                $l = str_replace('m', '', trim($size[0]));
                $w = str_replace('m', '', trim($size[2]));
                $imo = $html->filter('.group-ib > b')->eq(0)->text();
                echo $l . "\n";
                echo $w . "\n";
                if (empty($vessle['length'])) {
                    $this->vessle['length'] = $l;
                    $this->vessle['beam'] = $w;
                }
            }

            if (empty($vessle['imo']) and ! empty($imo)) {
                $this->vessle['imo'] = $imo;
            }
        } catch (RequestException $e) {
            $this->errors[] = Psr7\str($e->getRequest());
        }
    }

    public function output($message) {
        if (is_array($message)) {
            $message = implode("\n", $message);
        }
        if ($this->output) {
            echo $message . "\n";
        }
    }

}
