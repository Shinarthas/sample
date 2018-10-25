<?php

class ControllerGoogleapiEmail extends Controller {
    private $error = array();
    const APPLICATION_NAME='***';
    const REDIRECT_URL='***';
    const CREDENTIALS_PATH='***';
    const CLIENT_SECRET_PATH='***';

    private $details=[
      /*[
          'name'=>'art',
          'redirect_url'=>'***',
          'app_name'=>'***',
          'secret'=>'***',
          'credentials'=>'***',
          'email'=>'***'
      ],*/
      [
          'name'=>'sales',
          'redirect_url'=>'***',
          'app_name'=>'***',
          'secret'=>'***',
          'credentials'=>'***',
          'email'=>'sales@createashirt.com'
      ],
        [
            'name'=>'marketing',
            'redirect_url'=>'***',
            'app_name'=>'***',
            'secret'=>'***',
            'credentials'=>'***',
            'email'=>'***'
        ]
    ];

    public function index() {
        require_once __DIR__ . '/vendor/autoload.php';


        if(isset($this->request->get['limit'])){
            $limit=$this->request->get['limit'];
        }else{
            $limit=1;
        }


        $garparams="";// url params
        $data['details']=[];
        foreach ($this->details as $detail){
            $client = $this->getClient($detail['redirect_url'],$detail['app_name'],$detail['secret'],$detail['credentials']);
            $service = new Google_Service_Gmail($client);
            if(isset($this->request->get[$detail['name']])){
                $page_token=strval($this->request->get[$detail['name']]);
            }else{
                $page_token='';
            }
            //$tmp=$this->listThreads($service,'me', 10,$page_token);
            //get messages
            $tmp=$this->listMessages($service,'me', 25,$page_token);
            $tmp2=$tmp['messages'];//threads
            $garparams.=$detail['name']."=".$tmp['next_page_token'].'&';
            // only 1 msg per thread
            $tmp2 = array_unique(array_map(function ($i) { return $i['threadId']; }, $tmp2));

            //get threads
            foreach ($tmp2 as $thread){
                //$messages[]=$this->getThread($service,'me', $thread['id']);
                $messages[]=$this->getThread($service,'me', $thread);
            }

            //$messages=$tmp['messages'];
            $inBox=[];
            $allmsgArr=[];
            $data['details'][]=[
                'name'=>$detail['name'],
                'email'=>$detail['email'],
                'next_page_token'=>$tmp['next_page_token'],
            ];

            $data['threads']=[];

            //process threads
            for ($i=0;$i<count($messages);$i++){
                $tm=$messages[$i];
                $data['threads'][$i]=[];
                foreach ($tm as $m){
                    //$resp=$this->getMessage($service,'me', $m['id']);
                    $payload = $m->getPayload();
                    //print_r($m);
                    $tmp2=json_decode(json_encode($m['payload']['headers']),true);
                    $key = array_search('Message-ID', array_column($tmp2,'name'));
                    if($key==0 && $m['payload']['headers'][$key]['name']!='Message-ID'){
                        $key = array_search('Message-Id', array_column($tmp2,'name'));
                    }
                    $part1 = $m['payload']['headers'][$key]['value'];

                    $msgArr['reply_to']=$part1;
                    $msgArr['gmailmsgid'] = $m->getId();
                    $msgArr['threadId'] = $m['threadId'];
                    $msgArr['snippet'] = $m['snippet'];
                    // Retrieving the subject and "from" email address
                    foreach($payload->getheaders() as $oneheader)
                    {
                        if($oneheader['name'] == 'Subject')
                            $msgArr['subject'] = $oneheader['value'];
                        if($oneheader['name'] == 'From'){
                            $msgArr['fromaddress'] = substr($oneheader['value'], strpos($oneheader['value'], '<')+1, -1);
                            $msgArr['name']=strip_tags($oneheader['value']);
                        }
                        if($oneheader['name'] == 'Reply-To'){
                            $tmpstr0=str_replace('"', "", $oneheader['value']);
                            $tmpstr0=str_replace("'", "", $tmpstr0);
                            $msgArr['reply_to_true']=strip_tags($tmpstr0);
                        }
                        if($oneheader['name'] == 'To' ){
                            $tmpstr0=str_replace('"', "", $oneheader['value']);
                            $tmpstr0=str_replace("'", "", $tmpstr0);
                            $msgArr['reply_to_fake']=strip_tags($tmpstr0);
                        }
                        if($oneheader['name'] == 'Date'){
                            $tmp0=explode(',',$oneheader['value']);
                            //$tmp0=substr(end($tmp0), 0, -6);
                            $tmp0=end($tmp0);
                            $msgArr['date'] = $tmp0;
                        }



                    }

                    if($payload['body']['size'] > 0)
                        $msgArr['textplain'] = $payload['body']['data'];
                    // Else, iterate over each message part and continue to dig if necessary
                    else
                        $this->iterateParts($payload, $m->getId(), $service, $msgArr);

                    /*
                    foreach ($resp['payload']['parts'] as $p){
                        $tmp = strtr($p['body']['data'],'-_', '+/');
                        echo chunk_split(base64_decode($tmp));
                    }
                    */
                    $inBox[]=$m;
                    $data['threads'][$i][]=$msgArr;
                    $msgArr=[];
                }
            }

            // подгрузить изобрадения для последнего
            end($data['threads']);         // move the internal pointer to the end of the array
            $keyT = key($data['threads']);  // fetches the key of the element pointed to by the internal pointer
            end($data['threads'][$keyT]);
            $keyA=key($data['threads'][$keyT]);


            if(isset($data['threads'][$keyT][$keyA]['attachments'])){
                $attachments=$data['threads'][$keyT][$keyA]['attachments'];
                for($i=0;$i<count($attachments);$i++){
                    $a=$attachments[$i];
                    if(
                        (
                            $a['mimetype']=='image/gif' ||
                            $a['mimetype']=='image/jpeg' ||
                            $a['mimetype']=='image/pjpeg' ||
                            $a['mimetype']=='image/png' ||
                            $a['mimetype']=='image/svg+xml'
                        ) && $a['size']<500000
                    ){
                        $data['threads'][$keyT][$keyA]['attachments'][$i]['data']=$this->getAttachmentInner(
                            $data['threads'][$keyT][$keyA]['attachments'][$i]['attachmentId'],$data['threads'][$keyT][$keyA]['gmailmsgid'],
                            $detail['redirect_url'],$detail['app_name'],$detail['secret'],$detail['credentials']
                        );
                    }
                }
            }
            // код для embed cid картинок
            for ($k=0;$k<count($data['threads']);$k++){
                for($j=0;$j<count($data['threads'][$k]);$j++){
                    if(isset($data['threads'][$k][$j]['attachments'])){
                        $attachments=$data['threads'][$k][$j]['attachments'];
                        for($i=0;$i<count($attachments);$i++){
                            $a=$attachments[$i];
                            if(
                                (
                                    $a['mimetype']=='image/gif' ||
                                    $a['mimetype']=='image/jpeg' ||
                                    $a['mimetype']=='image/pjpeg' ||
                                    $a['mimetype']=='image/png' ||
                                    $a['mimetype']=='image/svg+xml'
                                ) && $a['size']<500000 && $a['class']=='embed' && isset($a['embed_id'])
                            ){
                                $data['threads'][$k][$j]['attachments'][$i]['data']=$this->getAttachmentInner(
                                    $data['threads'][$k][$j]['attachments'][$i]['attachmentId'],$data['threads'][$k][$j]['gmailmsgid'],
                                    $detail['redirect_url'],$detail['app_name'],$detail['secret'],$detail['credentials']
                                );
                            }
                        }
                    }
                }
            }

            $inBox=[];
            $allmsgArr=[];
            foreach ($messages as $tm){
                $m=end($tm);

                $resp=$this->getMessage($service,'me', $m['id']);
                if(is_int($resp)) continue;
                $payload = $resp->getPayload();

                $tmp2=json_decode(json_encode($resp['payload']['headers']),true);
                $key = array_search('Message-ID', array_column($tmp2,'name'));
                if($key==0 && $resp['payload']['headers'][$key]['name']!='Message-ID'){
                    $key = array_search('Message-Id', array_column($tmp2,'name'));
                }
                $part1 = $resp['payload']['headers'][$key]['value'];

                $msgArr['reply_to']=$part1;
                $msgArr['gmailmsgid'] = $m->getId();
                $msgArr['threadId'] = $m['threadId'];
                // Retrieving the subject and "from" email address
                foreach($payload->getheaders() as $oneheader)
                {
                    if($oneheader['name'] == 'Subject')
                        $msgArr['subject'] = $oneheader['value'];
                    if($oneheader['name'] == 'From')
                        $msgArr['fromaddress'] = substr($oneheader['value'], strpos($oneheader['value'], '<')+1, -1);
                }

                if($payload['body']['size'] > 0)
                    $msgArr['textplain'] = $payload['body']['data'];
                // Else, iterate over each message part and continue to dig if necessary
                else
                    $this->iterateParts($payload, $m->getId(), $service, $msgArr);

                $inBox[]=$resp;
                $allmsgArr[]=$msgArr;


            }
            $data['all_email_data'][]=[
                'data'=>$data['threads'],
                'email'=>$detail['email'],
                'name'=>$detail['name']
            ];
            $data['threads']=[];
            $inBox=[];
            $allmsgArr=[];
            $msgArr=[];
            $messages=[];
        }
        //$data['allmsgArr']=$allmsgArr;
//$garparams
        $data['nextpage']=$this->url->link('googleapi/email', $garparams.'token=' . $this->session->data['token']);
        $data['getAttachment'] = $this->url->link('googleapi/email/getAttachment', 'token=' . $this->session->data['token']);
        $data['sendReply'] = $this->url->link('googleapi/email/sendReply', 'token=' . $this->session->data['token']);
        $data['sendNew'] = $this->url->link('googleapi/email/sendNew', 'token=' . $this->session->data['token']);


        $data['address'] = $this->config->get('config_address');
        $data['telephone'] = $this->config->get('config_telephone');

        // new email sign
        $userid=$this->user->getId();
        $sql1="select firstname, lastname from " . DB_PREFIX . "user where user_id=$userid";
        $res1=$this->db->query($sql1);

        $data['firstname']=$res1->row['firstname'];
        $data['lastname']=$res1->row['lastname'];


        $data['lastname']=$res1->row['lastname'];



        $data['next_page_token']=$tmp['next_page_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('googleapi/email.tpl', $data));
    }
    private function getClient($redirect_url,$app_name,$secret,$credentials) {

        $client = new Google_Client();
        $client->setRedirectUri($redirect_url);
        $client->setApplicationName($app_name);
        $client->setScopes(Google_Service_Gmail::MAIL_GOOGLE_COM);
        $client->setAuthConfig($secret);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        // Load previously authorized credentials from a file.
        $credentialsPath = $this->expandHomeDirectory($credentials);
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
    private function expandHomeDirectory($path) {
        $homeDirectory = getenv('HOME');
        if (empty($homeDirectory)) {
            $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }
        return str_replace('~', realpath($homeDirectory), $path);
    }
    private function listMessages($service, $userId='me',$limit=50, $pageToken='') {
        //$pageToken = NULL;
        $nextPageToken='';
        $messages = array();
        $opt_param = array();
        $opt_param['maxResults']=$limit;
        //$opt_param['q']='in:inbox';
        $opt_param['q']='***';
        if($pageToken!=''){
            $opt_param['pageToken']=$pageToken;
        }
        try {

            if ($pageToken) {
                $opt_param['pageToken'] = $pageToken;
            }
            $pageToken='';
            $messagesResponse = $service->users_messages->listUsersMessages($userId, $opt_param);
            if ($messagesResponse->getMessages()) {
                $messages = array_merge($messages, $messagesResponse->getMessages());
                $nextPageToken = $messagesResponse->getNextPageToken();
            }
        } catch (Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }

        return ['messages'=>$messages, 'next_page_token'=>$nextPageToken];
    }
    private function listThreads($service, $userId='me',$limit=50, $pageToken='') {
        $threads = array();
        $opt_param = array();
        $opt_param['maxResults']=$limit;
        //$opt_param['q']='in:inbox';
        $opt_param['q']='***';
        if($pageToken!=''){
            $opt_param['pageToken']=$pageToken;
        }
            try {
                $threadsResponse = $service->users_threads->listUsersThreads($userId, $opt_param);
                if ($threadsResponse->getThreads()) {
                    $threads = array_merge($threads, $threadsResponse->getThreads());
                    $nextPageToken = $threadsResponse->getNextPageToken();
                }
            } catch (Exception $e) {
                print 'An error occurred: ' . $e->getMessage();
                $pageToken = NULL;
            }


        return ['messages'=>$threads, 'next_page_token'=>$nextPageToken];
    }

    //для контента и атачментов
    private function iterateParts($obj, $msgid,&$service,&$msgArr) {

        //global $msgArr;
        //global $service;


        foreach($obj as $parts)
        {
            // if found body data
            if($parts['body']['size'] > 0)
            {
                // plain text representation of message body
                if($parts['mimeType'] == 'text/plain')
                {
                    $msgArr['textplain'] = $parts['body']['data'];
                }
                // html representation of message body
                else if($parts['mimeType'] == 'text/html')
                {
                    $msgArr['texthtml'] = $parts['body']['data'];
                }
                // if it's an attachment
                else if(!empty($parts['body']['attachmentId']))
                {
                    $attachArr['mimetype'] = $parts['mimeType'];
                    $attachArr['filename'] = $parts['filename'];
                    $attachArr['size'] = $parts['body']['size'];
                    $attachArr['attachmentId'] = $parts['body']['attachmentId'];
                    $attachArr['class']='file';
                    if(strpos($attachArr['filename'],'.')===false && strpos($attachArr['filename'], 'embed') !== false){
                        $tmpPart=json_decode(json_encode($parts['headers']),true);
                        $key = array_search('Content-ID', array_column($tmpPart,'name'));
                        if($key==0 && $parts['headers']){
                            $key = array_search('Content-Id', array_column($tmpPart,'name'));
                        }
                        $attachArr['class']='embed';
                        $attachArr['embed_id']=$parts['headers'][$key]['value'];
                        $attachArr['embed_id']= substr($parts['headers'][$key]['value'], strpos($parts['headers'][$key]['value'], '<')+1, -1);
                    }

                    // the message holds the attachment id, retrieve it's data from users_messages_attachments
                    $attachmentId_base64 = $parts['body']['attachmentId'];
                    //$single_attachment = $service->users_messages_attachments->get('me', $msgid, $attachmentId_base64);

                    //$attachArr['data'] = $single_attachment->getData();

                    $msgArr['attachments'][] = $attachArr;
                }
            }

            // if there are other parts inside, go get them
            if(!empty($parts['parts']) && !empty($parts['mimeType']) && empty($parts['body']['attachmentId']))
            {
                $this->iterateParts($parts->getParts(), $msgid,$service,$msgArr);
            }

        }
    }
    private function getMessage($service, $userId, $messageId) {
        try {
            $message = $service->users_messages->get($userId, $messageId);
            return $message;
        } catch (Exception $e) {
            return 0;
        }
    }
    private function getThread($service, $userId, $threadId) {
            //$thread = $service->users_threads->get($userId, $threadId,['format'=>'minimal']);
            $thread = $service->users_threads->get($userId, $threadId);
            $messages = $thread->getMessages();
            $msgCount = count($messages);
            return $messages;
    }

    public function getAttachment()
    {

        require_once __DIR__ . '/vendor/autoload.php';

        $attachmentId=$this->request->post['attachmentId'];
        $messageId=$this->request->post['messageId'];
        $email=$this->request->post['email'];

        $key = array_search($email, array_column($this->details,'email'));
        if($this->details[$key]['email']!=$email){
            //echo $email;
            return 0;
        }
        $detail=$this->details[$key];

        $client = $this->getClient($detail['redirect_url'],$detail['app_name'],$detail['secret'],$detail['credentials']);
        $service = new Google_Service_Gmail($client);
        $single_attachment = $service->users_messages_attachments->get('me', $messageId, $attachmentId);
        $data=strtr($single_attachment->getData(),'-_', '+/');
        echo $data;


    }

    private function getAttachmentInner($attachmentId,$messageId,$redirect_url,$app_name,$secret,$credentials)
    {
        $client = $this->getClient($redirect_url,$app_name,$secret,$credentials);
        $service = new Google_Service_Gmail($client);
        $single_attachment = $service->users_messages_attachments->get('me', $messageId, $attachmentId);
        $data=strtr($single_attachment->getData(),'-_', '+/');
        return $data;


    }


    public function sendReply()
    {
        // определяем емаил
        $email=$this->request->post['email'];
        $key = array_search($email, array_column($this->details,'email'));
        if($this->details[$key]['email']!=$email){
            echo $email;
            return 0;
        }
        $detail=$this->details[$key];

        $uploadedFiles=[];
        $uploadedFileNames=[];
        $total=0;
        if(isset($_FILES['files']))
            $total = count($_FILES['files']['name']);

        // Loop through each file
        for($i=0; $i<$total; $i++) {
            //Get the temp file path
            $tmpFilePath = $_FILES['files']['tmp_name'][$i];
            $uploadOk = 1;
            //Make sure we have a filepath
            if ($tmpFilePath != ""){
                //Setup our new file path

                $newFilePath = "./uploads/" . $_FILES['files']['name'][$i];
                if (file_exists($newFilePath)) {
                    $uploadOk = 0;
                }
                if ($_FILES["files"]["size"][$i] > 5000000) {
                    $uploadOk = 0;
                }
                //Upload the file into the temp dir
                if ($uploadOk == 0) {
                    //echo "Sorry, your file was not uploaded.";
                } else {
                    if (move_uploaded_file($_FILES["files"]["tmp_name"][$i], $newFilePath)) {
                        $uploadedFiles[]=$newFilePath;
                        $uploadedFileNames[]=$_FILES['files']['name'][$i];
                        //echo $newFilePath;
                    }
                }
            }
        }


        require_once __DIR__ . '/vendor/autoload.php';
        require_once 'phpmailer/PHPMailer.php';
        require_once 'phpmailer/Exception.php';
        require_once 'phpmailer/SMTP.php';
        require_once 'GmailWrapper/Messages.php';


        $client = $this->getClient($detail['redirect_url'],$detail['app_name'],$detail['secret'],$detail['credentials']);
        $service = new Google_Service_Gmail($client);





        $from_email = $detail['email'];

        $userid=$this->user->getId();
        $sql1="select firstname, lastname from " . DB_PREFIX . "user where user_id=$userid";
        $res1=$this->db->query($sql1);

        $from_name = $res1->row['firstname']." ".$res1->row['lastname'];
        $threadId=$this->request->post['threadId'];
        $messegaId=$this->request->post['replyId'];
        $send_to=$this->request->post['send_to'];
        $reply_to=html_entity_decode($this->request->post['reply_to']);
        $subject=html_entity_decode($this->request->post['subject']);
        $body=html_entity_decode($this->request->post['message']);
        $html=html_entity_decode($this->request->post['prevHtml']);

        $body.="<br/><br/>------------------------------<br/><br/>";

        $body.=$html;


        $message = new Google_Service_Gmail_Message();
        $optParam = array();
        $referenceId = '';
        $optParam['threadId'] = $threadId;

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->IsHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->From = $from_email;
        //$mail->addReplyTo($part1);
        $mail->FromName = $from_name;
        for($i=0;$i<count($uploadedFiles);$i++){
            $mail->AddAttachment($uploadedFiles[$i],
                $uploadedFileNames[$i]);
        }
        $mail->addAddress($send_to);
        $mail->Subject = $subject;
        $mail->Body = $mail->msgHTML($body);
        $mail->AddCustomHeader("In-Reply-To: " . $reply_to);
        $mail->AddCustomHeader("References: " . $reply_to);
        $mail->preSend();
        $mime = $mail->getSentMIMEMessage();

        $raw = $this->Base64UrlEncode($mime);
        $message->setRaw($raw);

        //закоментить если не хочу это себе
        $message->setThreadId($threadId);
        //

        $response = $service->users_messages->send('me', $message);

        // удалить временніе файлы
        foreach ($uploadedFiles as $file){
            if (file_exists($file)) {
                unlink($file);
            }
        }

    }

    public function sendNew()
    {
        // определяем емаил
        $email=$this->request->post['email'];
        $key = array_search($email, array_column($this->details,'email'));
        if($this->details[$key]['email']!=$email){
            echo $email;
            return 0;
        }
        $detail=$this->details[$key];

        $uploadedFiles=[];
        $uploadedFileNames=[];
        $total=0;
        if(isset($_FILES['files']))
            $total = count($_FILES['files']['name']);

// Loop through each file
        for($i=0; $i<$total; $i++) {
            //Get the temp file path
            $tmpFilePath = $_FILES['files']['tmp_name'][$i];
            $uploadOk = 1;
            //Make sure we have a filepath
            if ($tmpFilePath != ""){
                //Setup our new file path

                $newFilePath = "./uploads/" . $_FILES['files']['name'][$i];
                if (file_exists($newFilePath)) {
                    $uploadOk = 0;
                }
                if ($_FILES["files"]["size"][$i] > 5000000) {
                    $uploadOk = 0;
                }
                //Upload the file into the temp dir
                if ($uploadOk == 0) {
                    //echo "Sorry, your file was not uploaded.";
                } else {
                    if (move_uploaded_file($_FILES["files"]["tmp_name"][$i], $newFilePath)) {
                        $uploadedFiles[]=$newFilePath;
                        $uploadedFileNames[]=$_FILES['files']['name'][$i];
                        //echo $newFilePath;
                    }
                }
            }
        }


        require_once __DIR__ . '/vendor/autoload.php';
        require_once 'phpmailer/PHPMailer.php';
        require_once 'phpmailer/SMTP.php';
        require_once 'GmailWrapper/Messages.php';


        $client = $this->getClient($detail['redirect_url'],$detail['app_name'],$detail['secret'],$detail['credentials']);
        $service = new Google_Service_Gmail($client);




        $from_email = $detail['email'];

        $userid=$this->user->getId();
        $sql1="select firstname, lastname from " . DB_PREFIX . "user where user_id=$userid";
        $res1=$this->db->query($sql1);

        $from_name = $res1->row['firstname']." ".$res1->row['lastname'];

        $send_to=$this->request->post['send_to'];
        //$reply_to=html_entity_decode($this->request->post['reply_to']);
        $subject=html_entity_decode($this->request->post['subject']);
        $body=html_entity_decode($this->request->post['message']);


        $message = new Google_Service_Gmail_Message();
        $optParam = array();
        $referenceId = '';


        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->IsHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->From = $from_email;
        //$mail->addReplyTo($part1);
        $mail->FromName = $from_name;
        for($i=0;$i<count($uploadedFiles);$i++){
            $mail->AddAttachment($uploadedFiles[$i],
                $uploadedFileNames[$i]);
        }
        $mail->addAddress($send_to);
        $mail->Subject = $subject;
        $mail->Body = $mail->msgHTML($body);

        $mail->preSend();
        $mime = $mail->getSentMIMEMessage();

        $raw = $this->Base64UrlEncode($mime);
        $message->setRaw($raw);

        //закоментить если не хочу это себе

        //

        $response = $service->users_messages->send('me', $message);

        foreach ($uploadedFiles as $file){
            if (file_exists($file)) {
                unlink($file);
            }
        }

    }
    //binary to base64
    private function Base64UrlEncode($string)
    {
        return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
    }
}
