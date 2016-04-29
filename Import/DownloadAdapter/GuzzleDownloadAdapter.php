<?php

namespace Giosh94mhz\GeonamesBundle\Import\DownloadAdapter;

use GuzzleHttp5Legacy\ClientInterface;
use GuzzleHttp5Legacy\Client;
use GuzzleHttp5Legacy\Event\CompleteEvent;
use GuzzleHttp5Legacy\Pool;
use GuzzleHttp5Legacy\Event\ProgressEvent;

class GuzzleDownloadAdapter extends AbstractDownloadAdapter
{
    /**
     * @var ClientInterface
     */
    protected $client;

    protected $directory;

    protected $requests;

    protected $downloadsSize;

    /**
     * @param ClientInterface $client Client object
     */
    public function __construct(ClientInterface $client = null)
    {
        $this->requests = array();

        if (! $client) {
            $this->client = new Client();
        } else {
            $this->client = $client;
        }
    }

    public function add($url)
    {
        $destFile = $this->getDestinationPath($url);
        $this->requests[] = array(
            'url' => $url,
            'file' => $destFile
        );

        return $destFile;
    }

    public function requestContentLength()
    {
        if ($this->downloadsSize === null) {
            $requests = array();
            foreach ($this->requests as $request) {
                $requests[] = $this->client->createRequest(
                    'HEAD',
                    $request['url']
                );
            }

            $contentLength = 0;
            $pool = new Pool($this->client, $requests, [
                'complete' => function (CompleteEvent $event) use (&$contentLength) {
                    $contentLength += $event->getResponse()->getHeader('Content-Length');
                }
            ]);
            $pool->wait();

            $this->downloadsSize = $contentLength;
        }

        return $this->downloadsSize;
    }

    public function download()
    {
        $progressFunctions = null;
        if ($this->getProgressFunction()) {
            $progressFunctions = $this->createProgressFunctions(
                array_fill(0, count($this->requests), 0)
            );
        }

        $requests = array();
        foreach ($this->requests as $i => $r) {
            $requests[] = $request = $this->client->createRequest(
                'GET',
                $r['url'],
                array('save_to' => $r['file'])
            );

            if ($progressFunctions !== null) {
                $f = $progressFunctions[$i];
                $request->getEmitter()->on('progress', function(ProgressEvent $event) use ($f) {
                    call_user_func($f, $event->downloadSize, $event->downloaded);
                });
            }
        }

        $pool = new Pool($this->client, $requests);
        $pool->wait();
    }

    public function clear()
    {
        $this->requests = array();
    }
}
