# Simple example how to run multiple processes in one cron job without risk of server overload 

- Run cronjob:
```shell
3 * * * /php_path/php /root_path/feed_generator.php
```
- The whole idea is represent in this piece of code:
```php
    public function __construct()
    {
        $scriptPath = dirname(__FILE__). DIRECTORY_SEPARATOR. 'feed_generator.php';
        $feeds = array();
        $feeds[] = "php {$scriptPath} zizito3 3 1";
        $feeds[] = "php {$scriptPath} zizito3 3 2";
        $feeds[] = "php {$scriptPath} zizito3 5 3";
        $feeds[] = "php {$scriptPath} zizito3 4 4";
        $feeds[] = "php {$scriptPath} zizito3 3 1 BGN";
        $feeds[] = "php {$scriptPath} zizito3 3 1 EUR";
        $feeds[] = "php {$scriptPath} zizito3 4 4 RON";
        foreach($feeds as $feed){
            $processId = exec($feed . " > /dev/null 2>&1 & echo $!;");
            while($this->checkIfProcessRunning($processId)){
                sleep(10);
            }
        }
        echo ("All feeds were processed successfully! XML files are contained inside /public/ directory! "); die;
    }

    private function checkIfProcessRunning($process){

        $return = false;
        if(file_exists("/proc/{$process}")){
            $return = true;
            echo ' ... ';
        }
        return $return;
    }
```