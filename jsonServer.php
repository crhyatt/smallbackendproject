<?php
header('Content-Type: application/json');
session_start();

/**
 * Class JSONSERVER
 * Description:  The JSONSERVER class takes input
 * from the url and outputs a json representation
 * of the database.  There are three key formats
 * available: linechart, table, and multiplepage.
 */
class JSONSERVER
{
    private $jsonType;
    private $mysql;
    private $strCompany;
    private $error;
    private $config;

    /**
     * JSONSERVER constructor.
     * @param $jsonT
     * @param $strComp
     * The constructor takes the arguments given
     * by the user through the url and builds the class.
     * The constructor will also initiate the mySQL datebase class.
     */
    public function __construct($jsonT, $strComp)
    {
        $this->jsonType = $jsonT;
        $this->strCompany = $strComp;

        $iniFile = "/Users/salty/PhpstormProjects/EngagementLabsTest/" . "/config.ini";
        $this->config = parse_ini_file($iniFile);
        $this->mysql = new mysqli($this->config['servername'], $this->config['username'],
            $this->config['password'], $this->config['dbname'], $this->config['port'],
            $this->config['sock']);

        $this->error = false;


    }

    /**
     * JSONSERVER getJSON
     * @return string
     * Description: Queries the mySQL DB for the difference
     * in the fan_counts between timestamps and returns a
     * json file depending on the user requested type.
     */
    public function getJSON()
    {

        $results = null;
        if($this->jsonType === "multiplepage")
            $query = "SELECT company, date, fanCount - 
                            (SELECT a.fanCount from fancount a
                              WHERE date = (
                                    SELECT max(date) as date
                                    FROM fancount x 
                                    WHERE x.date < y.date)
                              AND company = y.company)
                              AS value
                      FROM fancount y
                      ORDER BY date ASC";
        else
        {
            /**
             * Queries for only a give company name.
             */
            $query = "SELECT company, date, fanCount -
                             (SELECT a.fanCount from fancount a
                              WHERE date = (
                                    SELECT max(date) as date
                                    FROM fancount x
                                    WHERE x.date < y.date)
                              AND company = y.company)
                              AS value
                      FROM fancount y
                      WHERE company = '".$this->strCompany."'
                      ORDER BY date ASC";
        }
        $results = $this->mysql->query($query);
        if($results != false)
        {
            echo "<br>Query Success<br>";

        }
        else
        {
            echo "<br>".mysqli_error($this->mysql)."<br>";
            $this->error = true;
        }

        return $this->createJson($results);
    }

    /**
     * JSONSERVER
     * @param $results
     * @return string
     * Description:  This function takes the results of the
     * mySQL query and formats it in to json file.
     */
    public function createJson($results)
    {

        if($results === false)
        {
            $hashJson['error'] = $this->error;
            return json_encode($hashJson,JSON_PRETTY_PRINT);
        }
        /**
         * SWITCH statement for each of the request types.
         */
        switch ($this->jsonType)
        {
            case "linechart":
                //echo "Case Line<br>";
                $data = $results->fetch_all(1);
                $hashJson['error'] = $this->error;
                $hashJson['data'] = $data;

                break;
            case "table":
                //echo "Case Table<br>";
                while($row = $results->fetch_assoc())
                {
                    $date[] = $row['date'];
                    $value[] = $row['value'];
                }
                $hashJson['error'] = $this->error;
                $hashJson['data'] = array_combine($date, $value);


                break;
            case "multiplepage":
                //echo "Case Multi<br>";
                $hashJson['error'] = $this->error;
                while($row = $results->fetch_assoc())
                {
                    $hashJson[$row['company']][] = array('value' => $row['value'], 'date' => $row['date']);
                }

                break;
            default:
                //echo "Default Case<br>";
                $hashJson['error'] = true;
                break;
        }

        return json_encode($hashJson, JSON_PRETTY_PRINT);
    }
}


$json = new JSONSERVER($_GET['format'], $_GET['pageid']);


echo $json->getJSON();

