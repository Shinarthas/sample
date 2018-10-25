<?php
//НЕБОЛЬШОЙ ПАРСЕР ВЕБ САЙТОМ ГУГЛ ИНТЕГРАЦИЕЙ
ini_set('max_execution_time', 3600); //300 seconds = 5 minutes
set_time_limit(3600);

require_once  'simple_html_dom.php';
include 'xlsxwriter.class.php';
$writer = new XLSXWriter();

$column=4;
$cell=500;//какая строчка
$startCellPoint=$cell;
$cellLength=425;//длина записей
function getNameFromNumber($num) {
    $numeric = ($num - 1) % 26;
    $letter = chr(65 + $numeric);
    $num2 = intval(($num - 1) / 26);
    if ($num2 > 0) {
        return getNameFromNumber($num2) . $letter;
    } else {
        return $letter;
    }
}
function page_title($fp) {
    $res = preg_match("/<title>(.*)<\/title>/siU", $fp, $title_matches);
    if (!$res)
        return null;

    // Clean up title: remove EOL's and excessive whitespace.
    $title = preg_replace('/\s+/', ' ', $title_matches[1]);
    $title = trim($title);
    return $title;
}
require_once  'google-api-php-client-2.2.0/vendor/autoload.php';


define('APPLICATION_NAME', 'Google Sheets API PHP Quickstart');
define('CREDENTIALS_PATH', '***');
//define('CREDENTIALS_PATH', 'quickstart.json');
define('CLIENT_SECRET_PATH',  '***');
// If modifying these scopes, delete your previously saved credentials
define('SCOPES', implode(' ', array(
        Google_Service_Sheets::SPREADSHEETS )
));

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setDeveloperKey("MY_SIMPLE_API_KEY");
    $client->setScopes(SCOPES);
    $client->setAuthConfig(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Store the credentials to disk.
        if(!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
    $homeDirectory = getenv('HOME');
    if (empty($homeDirectory)) {
        $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
    }
    return str_replace('~', realpath($homeDirectory), $path);
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Sheets($client);


$spreadsheetId = '***';
$range = 'Sheet1!A'.$cell.':C'.($cell+$cellLength);
//$range = 'Class Data!B2:B50';

$response = $service->spreadsheets_values->get($spreadsheetId, $range);
$values = $response->getValues();



?>

<?php

//$GCSE_API_KEY = "***";
$GCSE_API_KEY = "***";
//$GCSE_SEARCH_ENGINE_ID = "***";
$GCSE_SEARCH_ENGINE_ID = "***";

$client2 = new Google_Client();
$client2->setApplicationName("My_App");
$client2->setDeveloperKey($GCSE_API_KEY);
$client->setScopes(SCOPES);
$client->setAuthConfig(CLIENT_SECRET_PATH);
$client->setAccessType('offline');
$service2 = new Google_Service_Customsearch($client2);
//$optParams = array("cx"=>$GCSE_SEARCH_ENGINE_ID,"start"=>"0","num"=>"19");
$optParams = array(
    "cx"=>$GCSE_SEARCH_ENGINE_ID,
    "start"=>1,
    "gl"=>"US",
    "exactTerms"=>"@"
);
$column3=1;
$cell3=1;

if (count($values) == 0) {
    print "No data found.\n";
} else {
    $googleSheetUrlsMany=array();
    foreach ($values as $row) {
        $results2=explode(', ',$row[2]);
       foreach($results2 as $k=>$item){
            if($k>=3){
                break;
            }
           //print_r($item);
           if($item!="No Page" && $item!=""){
               $tmpUrlName=$item;
               echo "\n**********$item********\n";
               echo "\n**********page parse********\n";
               //bypass antiscrap protection
               $opts = array('http' =>
                   array(
                       'method'  => 'GET',
                       'user_agent '  => "Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36",
                       'Referrer Policy'=>'no-referrer-when-downgrade',
                       'header' => array(
                           'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*\/*;q=0.8',
                           'Accept-Encoding:gzip, deflate',
                           'Accept-Language:ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4,uk;q=0.2',
                           'Cache-Control:max-age=0',
                           "Host:".parse_url($row[2])["host"],
                           'If-Modified-Since:Fri, 06 Oct 2017 11:25:03 GMT',
                           'Upgrade-Insecure-Requests:1',
                           'User-agent:Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36'
                       ),
                       'timeout'=>100,
                       'ignore_errors'=>true
                   )
               );
               $context  = stream_context_create($opts);

               $context = stream_context_create(array(
                   'http' => array(
                       'method' => "GET",
                       'header' =>
                           "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                           "Accept-Language: en-US,en;q=0.8\r\n".
                           "Keep-Alive: timeout=3, max=10\r\n",
                       "Connection: keep-alive",
                       'user_agent' => "User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.66 Safari/535.11",
                       "ignore_errors" => true,
                       "timeout" => 3
                   )
               ));

               $title='';
               $sitePhones=array();
               $siteEmails=array();

               $patternEmail = '/[a-z0-9_\-\+]+@[a-z0-9\-]+\.([a-z]{2,3})(?:\.[a-z]{2})?/i';
               $patternPhone = '/[0-9]{3}[\-][0-9]{6}|[0-9]{3}[\s][0-9]{6}|[0-9]{3}[\s][0-9]{3}[\s][0-9]{4}|[0-9]{9}|[0-9]{3}[\-][0-9]{3}[\-][0-9]{4}/';
               //preg_match_all($patternEmail, $siteContentText, $siteEmails);
               //echo $siteContent;
               //if(count($siteEmails[0])>3){
               if(true){
                   // Find tables
                   $html = file_get_html($item,false,$context);
                   $appendval=[];
                   if(!is_bool($html)){
                       $groupRows=array();
                       //foreach($html->find('table tbody tr') as $element){
                       foreach($html->find('table') as $table){
                           $prevElement=$table->previousSibling();
                           if(strlen($prevElement->plaintext)<35){
                               $appendval[0]= $prevElement->plaintext;
                           }

                           foreach($table->find('tbody tr') as $element) {
                               $tampleRow = array();
                               foreach ($element->find('td,th') as $td) {
                                   if ($td->hasAttribute('colspan')) {
                                       //echo $td->plaintext;
                                       $appendval[0] = $td->plaintext;
                                   }
                                   $specialChars = array("\v", "\b", "\0", "\r", "\n", "\t");
                                   $replaceChars = array("", "", "", "", "", "");

                                   $str = str_replace($specialChars, $replaceChars, $td->plaintext);
                                   $str = trim($str);
                                   $tampleRow[] = $str;
                               }

                               //если найден номер телефона, заносим в таблицу
                               $tr1=true;
                               for($i=1;$i<count($tampleRow) && count($tampleRow)>=1;$i++){
                                   if (preg_match($patternPhone, $tampleRow[$i])) {
                                       $tmp = $tampleRow[1];
                                       $tampleRow[1] = $tampleRow[$i];
                                       $tampleRow[$i] = $tmp;

                                       $tr1=false;
                                       break;
                                   }
                               }
                               if($tr1){
                                   $tr2=true;
                                   for($i=1;$i<count($tampleRow) && count($tampleRow)>=1;$i++){
                                       if ( trim($tampleRow[$i])=='') {
                                           // do stuff
                                           $tmp = $tampleRow[1];
                                           $tampleRow[1] = $tampleRow[$i];
                                           $tampleRow[$i] = $tmp;

                                           $tr2=false;
                                           break;
                                       }
                                   }
                                   if($tr2){
                                       $tampleRow[]=$tampleRow[1];
                                       $tampleRow[1]='';
                                   }

                               }
                                // пишем в xls
                               foreach ($tampleRow as $key => &$item) {
                                   if (preg_match($patternEmail, $item)) {
                                       // do stuff
                                       $tmp = $tampleRow[0];
                                       $tampleRow[0] = $tampleRow[$key];
                                       $tampleRow[$key] = $tmp;
                                       $rowValues = array_merge([strval($cell), $row[0], $tmpUrlName], $tampleRow, $appendval);

                                       $writer->writeSheetRow('Sheet1', $rowValues);
                                       $groupRows[] = $rowValues;
                                       continue;
                                   }
                               }
                           }

                       }
                       // пишем в гугл таблицу
                       if(count($groupRows)){
                           $valueInputOption="RAW";
                           $values3 =$groupRows;
                           $body = new Google_Service_Sheets_ValueRange(array(
                               'values' => ($groupRows)
                           ));
                           $params = array(
                               'valueInputOption' => $valueInputOption
                           );
                           $cell3+=count($groupRows);
                       }
                   }
               }
           }

       }
        $cell+=1;
        $column=4;

        echo "\n--------END------\n";
        echo "\n";
    }
    $writer->writeToFile('emails.xlsx');
    //print_r($values);
}
?>
