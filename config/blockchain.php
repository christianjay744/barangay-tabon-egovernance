<?php
function record_blockchain($documentNumber,$hash){
    $payload=json_encode(['documentNumber'=>$documentNumber,'hash'=>$hash]);
    $ch=curl_init('http://127.0.0.1:3001/record');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>4]);
    $response=curl_exec($ch);
    $err=curl_error($ch);curl_close($ch);
    if($response){
        $data=json_decode($response,true);
        if(is_array($data)&&($data['success']??false)) return ['status'=>'Recorded','tx'=>$data['tx']??null];
    }
    return ['status'=>'Pending','tx'=>null];
}
