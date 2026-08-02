<?php
//include '../vendor/autoload.php';
//use PHPMailer\PHPMailer\PHPMailer;
const APP_NAME = "Bank Pro";


function toast_alert($type, $msg, $title = false) {
    $valid = ['success', 'error', 'warning', 'info'];
    $method = in_array($type, $valid, true) ? $type : 'info';
    $alert_title = $title === false ? '' : $title;

    $msgEsc   = json_encode((string)$msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $titleEsc = json_encode((string)$alert_title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $methodEsc = json_encode($method);

    echo "<script>(window.__toasts = window.__toasts || []).push([{$methodEsc}, {$msgEsc}, {$titleEsc}]);</script>";
}

//CARD STATUS
function getCardStatus($status){
    switch ((string)$status['card_status']) {
        case '1': return '<span class="badge badge-success">ACTIVE</span>';
        case '2': return '<span class="badge badge-info">PROCESSING</span>';
        case '3': return '<span class="badge badge-warning">HOLD</span>';
        case '4': return '<span class="badge badge-danger">PAUSE</span>';
        default:  return '<span class="badge badge-light">UNKNOWN</span>';
    }
}

//CARD TYPE
function getCardType($type): string
{
    $card_number = explode(' ',$type['card_number']);
    $card_type = null;
    if (substr($card_number[0],'0','2')==='52'){
        return "MASTER";
    }elseif (substr($card_number[0],'0','2')==='40'){
      return "VISA";
    }elseif (substr($card_number[0],'0','2')==='67'){
        return "MAESTRO";
    }elseif(substr($card_number[0],'0','2')==='30'){
       return "DINERS";
    }elseif(substr($card_number[0],'0','2')==='62'){
       return "UNIONPAY";
    }elseif(substr($card_number[0],'0','2')==='37'){
        return "AMERICAN EXPRESS";
    }elseif(substr($card_number[0],'0','2')==='60'){
        return "DISCOVER";
    }elseif(substr($card_number[0],'0','2')==='35'){
        return "JCB";
    }else{
        return "INVALID";
    }
}
//WIRE TRANSACTION STATUS
function wireStatus($result){
    if ($result['wire_status'] === '0') {
        return '<span class="badge badge-secondary">In Progress</span>';
    }
    if($result['wire_status'] === '2'){
        return  '<span class="badge badge-danger">Hold</span>';
    }

    if($result['wire_status'] === '3') {
        return '<span class="badge badge-danger">Cancelled</span>';
    }

    if($result['wire_status'] === '1') {
        return '<span class="badge badge-success">Completed</span>';
    }
}

//CRYPTO TRANSACTION STATUS
function cryptoTransaction($result){
    if ($result['crypto_status'] === '0') {
        return '<span class="badge badge-secondary">In Progress</span>';
    }
    if($result['crypto_status'] === '2'){
        return  '<span class="badge badge-danger">Hold</span>';
    }

    if($result['crypto_status'] === '3') {
        return '<span class="badge badge-danger">Cancelled</span>';
    }

    if($result['crypto_status'] === '1') {
        return '<span class="badge badge-success">Completed</span>';
    }
}

//DOMESTIC TRANSACTION STATUS
function domesticTransaction($result){
    if ($result['dom_status'] === '0') {
        return '<span class="badge badge-secondary">In Progress</span>';
    }
    if($result['dom_status'] === '2'){
        return  '<span class="badge badge-danger">Hold</span>';
    }

    if($result['dom_status'] === '3') {
        return '<span class="badge badge-danger">Cancelled</span>';
    }

    if($result['dom_status'] === '1') {
        return '<span class="badge badge-success">Completed</span>';
    }
}


//USERS CURRENCY
function currency($row){
    if($row['acct_currency'] === 'USD'){
        return "$";
    }elseif($row['acct_currency'] === 'EUR'){
        return "&euro;";
    }
}
