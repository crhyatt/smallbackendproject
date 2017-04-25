<?php
session_start();
/**
 * Created by PhpStorm.
 * User: Christopher Hyatt
 * Date: 4/18/17
 * Time: 1:22 PM
 * Description:  EngagementLabMain.php will be called by
 * the command line on an hourly bases.  Each time the script is
 * called a mySQL DB is updated.  The script expects the input
 * from the user in the form of one or more page ids.  Facebook
 * and mySQL calls have been implemented using batch modes.
 * This batch capability enables the user to input more than
 * one pageid at a time.
 */

/**
 * Have Composer download the Facebook API
 */
require_once __DIR__ . '/vendor/autoload.php';


/**
 * Define the passable options.
 */
$longOpts = array
(
    "pageid::",
);

$shortOpts = "";
$shortOpts .= "p::";

var_dump($shortOpts);

$options = getopt($shortOpts,$longOpts);
var_dump($options);


/**
 * Class FANCOUNT
 * Description:  In order to prevent variable passing and
 * enable re-usability a class is implemented.  Both batch
 * and single call methods have been defined.
 */

class FANCOUNT
{
    private $fb;
    private $mysql;
    private $config;
    private $time;
    private $error;
    private $strCompany;
    private $arrCompany;

    /**
     * FANCOUNT constructor.
     * @param $companies
     * Description:  The constructor takes a csv list
     * or single company name.  It will instantiate both
     * the Facebook and mySQL classes.
     */
    public function __construct($companies)
    {

        var_dump($companies);
        $this->strCompany = $companies;
        /**
         * Convert csv list of companies into an array
         */
        $this->arrCompany = explode(',', $this->strCompany);
        var_dump($this->arrCompany);
        $this->error = false;

        $this->fb = new Facebook\Facebook([
            'app_id' => '1894597044090049',
            'app_secret' => 'caa9fd7b2f97e5661e2d8abefc733341',
            'default_graph_version' => 'v2.9'
        ]);
        /**
         * The mySQL server information has been
         * saved using a config.ini file.
         */
        $iniFile = __DIR__ . "/config.ini";
        $this->config = parse_ini_file($iniFile);
        $this->mysql = new mysqli($this->config['servername'], $this->config['username'],
                            $this->config['password'], $this->config['dbname'], $this->config['port'],
                            $this->config['sock']);
        /**
         * Create time handle to get UTC timestamp
         */
        $this->time = new DateTime('now',
            new DateTimeZone('UTC'));



    }


    /**
     * FANCOUNT destructor
     * @param none
     * Description: Closes the mySQL connection.
     */
    public function __destruct()
    {
        $this->mysql->close();
    }

    /**
     * FANCOUNT getFanCount
     * @param $company
     * @return int of fan_count
     * Description: Queries Facebook page for a given
     * company and returns the fan_count for that companies
     * page.
     */
    public function getFanCount($company)
    {
        $response = $this->fb->get("/".$company."?fields=fan_count",
            $this->fb->getApp()->getAccessToken());
        echo "<br>".$response->getGraphPage()['fan_count']."<br>";
        return $response->getGraphPage()['fan_count'];
    }

    /**
     * FANCOUNT getFanCountBatch
     * @return array
     * Description: Given an array of companies
     * a batch of queries has been used to get the
     * fan_count for each of those company pages.
     */
    public function getFanCountBatch()
    {

        foreach($this->arrCompany as $comp)
        {
            /**
             * Create array of queries
             */
            $request[] = $this->fb->request("GET", '/'.$comp.'?fields=fan_count');
        }
        try
        {
            /**
             * Send all queries
             */
            $response = $this->fb->sendBatchRequest($request,
                $this->fb->getApp()->getAccessToken());
        }
        catch(Facebook\Exceptions\FacebookResponseException $e)
        {
            die("Graph API Batch Request Error: ".$e->getMessage());
        }
        /**
         * For each response check for an error and
         * create a hash of the results.
         */
        foreach($response as $key => $res)
        {
            if($res->isError())
            {
                $error = $res->getThrownException();
                echo $key . " error: ". $error->getMessage();
            }
            else
            {

                $hashFan[] = array($this->arrCompany[$key] => $res->getGraphPage()['fan_count']);
            }
        }

        return $hashFan;

    }

    /**
     * FANCOUNT sendFanCountDB
     * @param $company
     * Description:  This function sends the a fan_count
     * for a single company to the DB.
     */
    public function sendFanCountDB($company)
    {
        /**
         * Get the fan_count using the single
         * company getFanCount method.
         */
        $fanCount = $this->getFanCount($company);
        /**
         * Get UTC timestamp
         */
        $date = $this->time->getTimestamp();

        $sql = "INSERT INTO fancount 
          (company, date, fanCount) VALUES ('".$company."','".$date."','".$fanCount."')";
        /**
         * Send query to mySQL
         */
        if($this->mysql->query($sql) === true)
        {
            echo "<br>Query Successfully Inserted<br>";

        }
        else
        {
            echo "<br>".mysqli_error($this->mysql)."<br>";
        }



    }

    /**
     * FANCOUNT sendFanCountDbBatch
     * @param $hashFan
     * Description: Batch method of sending fan_count
     * data to mySQL.  Using larger data sets batch size
     * will have to be limited and broken into pieces.
     */
    public function sendFanCountDbBatch($hashFan)
    {
        $date = $this->time->getTimestamp();

        foreach($hashFan as $key)
        {
            foreach($key as $comp => $value)
            {

                $c = $this->mysql->real_escape_string($comp);
                $d = $this->mysql->real_escape_string($date);
                $v = $this->mysql->real_escape_string($value);
                $sql[] = '("'.$c.'","'.$d.'", "'.$v.'")';
            }

        }

        /**
         * This method of using implode is easier than
         * the .= string method.
         */
        $query = "INSERT INTO fancount 
          (company, date, fanCount) VALUES ".implode(',', $sql);


        /**
         * Error checking
         */
        if($this->mysql->real_query($query) === true)
        {
            echo "<br>Query Successfully Inserted<br>";
        }
        else
        {
            echo "<br>".mysqli_error($this->mysql)."<br>";
        }


    }

}

/**
 * Create new FANCOUNT instance
 */
if($options['pageid'] === null)
    $strOps = $options['p'];
else
    $strOps = $options['pageid'];
$fan = new FANCOUNT($strOps);
/**
 * Get hash of fan_count
 * company name => fan_count
 */
$hashFan = $fan->getFanCountBatch();
/**
 * Send fan_count hash to DB
 */
$fan->sendFanCountDbBatch($hashFan);




