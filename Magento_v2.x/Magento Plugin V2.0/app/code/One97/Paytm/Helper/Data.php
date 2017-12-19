<?php
namespace One97\Paytm\Helper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Helper\AbstractHelper;
class Data extends AbstractHelper
{
    protected $session;
    public $PAYTM_PAYMENT_URL_PROD = "https://secure.paytm.in/oltp-web/processTransaction";
    public $STATUS_QUERY_URL_PROD = "https://secure.paytm.in/oltp/HANDLER_INTERNAL/TXNSTATUS";
    public $NEW_STATUS_QUERY_URL_PROD = "https://secure.paytm.in/oltp/HANDLER_INTERNAL/getTxnStatus";
	
    public $PAYTM_PAYMENT_URL_TEST = "https://pguat.paytm.com/oltp-web/processTransaction";
    public $STATUS_QUERY_URL_TEST = "https://pguat.paytm.com/oltp/HANDLER_INTERNAL/TXNSTATUS";
    public $NEW_STATUS_QUERY_URL_TEST = "https://pguat.paytm.com/oltp/HANDLER_INTERNAL/getTxnStatus";
    public function __construct(Context $context, \Magento\Checkout\Model\Session $session) {
        $this->session = $session;
        parent::__construct($context);
    }
    public function cancelCurrentOrder($comment) {
        $order = $this->session->getLastRealOrder();
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
            return true;
        }
        return false;
    }
    public function restoreQuote() {
        return $this->session->restoreQuote();
    }
    public function getUrl($route, $params = []) {
        return $this->_getUrl($route, $params);
    }
    
    public function pkcs5_pad_e($text, $blocksize) {
	$pad = $blocksize - (strlen($text) % $blocksize);
	return $text . str_repeat(chr($pad), $pad);
    }
	
  
	
	public function encrypt_e_openssl($input, $ky){
	$iv = "@@@@&&&&####$$$$";
	$data = openssl_encrypt ( $input , "AES-128-CBC" , $ky, 0, $iv );
	return $data;
}

public function decrypt_e_openssl($crypt, $ky){
	$iv = "@@@@&&&&####$$$$";
	$data = openssl_decrypt ( $crypt , "AES-128-CBC" , $ky, 0, $iv );
	return $data;
}

public function generateSalt_e($length) {
	$random = "";
	srand((double) microtime() * 1000000);

	$data = "AbcDE123IJKLMN67QRSTUVWXYZ";
	$data .= "aBCdefghijklmn123opq45rs67tuv89wxyz";
	$data .= "0FGH45OP89";

	for ($i = 0; $i < $length; $i++) {
		$random .= substr($data, (rand() % (strlen($data))), 1);
	}

	return $random;
}

public function checkString_e($myvalue) {
	//$myvalue = ltrim($value);
	//$myvalue = rtrim($myvalue);
	if ($myvalue == 'null')
		$myvalue = '';
	return $myvalue;
}

public function getChecksumFromArray($arrayList, $key, $sort=1) {
	if ($sort != 0) {
		ksort($arrayList);
	}
	$str = $this->getArray2Str($arrayList);
	$salt = $this->generateSalt_e(4);
	$finalString = $str . "|" . $salt;
	$hash = hash("sha256", $finalString);
	$hashString = $hash . $salt;
	$checksum = $this->encrypt_e_openssl($hashString, $key);
	
	return $checksum;
}

public function getChecksumFromString($str, $key) {
	
	$salt = $this->generateSalt_e(4);
	$finalString = $str . "|" . $salt;
	$hash = hash("sha256", $finalString);
	$hashString = $hash . $salt;
	$checksum = $this->encrypt_e_openssl($hashString, $key);
	return $checksum;
}

public function verifychecksum_e($arrayList, $key, $checksumvalue) {
	$arrayList = $this->removeCheckSumParam($arrayList);
	ksort($arrayList);
	$str = $this->getArray2Str($arrayList);
	$paytm_hash = $this->decrypt_e_openssl($checksumvalue, $key);
	$salt = substr($paytm_hash, -4);

	$finalString = $str . "|" . $salt;

	$website_hash = hash("sha256", $finalString);
	$website_hash .= $salt;

	$validFlag = "FALSE";
	if ($website_hash == $paytm_hash) {
		$validFlag = "TRUE";
	} else {
		$validFlag = "FALSE";
	}
	return $validFlag;
}

public function verifychecksum_eFromStr($str, $key, $checksumvalue) {
	$paytm_hash = $this->decrypt_e_openssl($checksumvalue, $key);
	$salt = substr($paytm_hash, -4);

	$finalString = $str . "|" . $salt;

	$website_hash = hash("sha256", $finalString);
	$website_hash .= $salt;

	$validFlag = "FALSE";
	if ($website_hash == $paytm_hash) {
		$validFlag = "TRUE";
	} else {
		$validFlag = "FALSE";
	}
	return $validFlag;
}

public function getArray2Str($arrayList) {
	$findme   = 'REFUND';
	$findmepipe = '|';
	$paramStr = "";
	$flag = 1;	
	foreach ($arrayList as $key => $value) {
		$pos = strpos($value, $findme);
		$pospipe = strpos($value, $findmepipe);
		if ($pos !== false || $pospipe !== false) 
		{
			continue;
		}
		
		if ($flag) {
			$paramStr .= $this->checkString_e($value);
			$flag = 0;
		} else {
			$paramStr .= "|" . $this->checkString_e($value);
		}
	}
	return $paramStr;
}


public function redirect2PG($paramList, $key) {
	$hashString = $this->getchecksumFromArray($paramList);
	$checksum = $this->encrypt_e_openssl($hashString, $key);
}

public function removeCheckSumParam($arrayList) {
	if (isset($arrayList["CHECKSUMHASH"])) {
		unset($arrayList["CHECKSUMHASH"]);
	}
	return $arrayList;
}

public function getTxnStatus($requestParamList) {
	return $this->callAPI(PAYTM_STATUS_QUERY_URL, $requestParamList);
}

public function initiateTxnRefund($requestParamList) {
	$CHECKSUM = $this->getChecksumFromArray($requestParamList,PAYTM_MERCHANT_KEY,0);
	$requestParamList["CHECKSUM"] = $CHECKSUM;
	return callAPI(PAYTM_REFUND_URL, $requestParamList);
}

function callAPI($apiURL, $requestParamList) {
	$jsonResponse = "";
	$responseParamList = array();
	$JsonData =json_encode($requestParamList);
	$postData = 'JsonData='.urlencode($JsonData);
	$ch = curl_init($apiURL);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);                                                                  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                         
	'Content-Type: application/json', 
	'Content-Length: ' . strlen($postData))                                                                       
	);  
	$jsonResponse = curl_exec($ch);   
	$responseParamList = json_decode($jsonResponse,true);
	return $responseParamList;
}

    function callNewAPI($apiURL, $requestParamList)
	{
	    $jsonResponse      = "";
	    $responseParamList = array();
	    $JsonData          = json_encode($requestParamList);
	    $postData          = 'JsonData=' . urlencode($JsonData);
	    $ch                = curl_init($apiURL);
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Content-Length: ' . strlen($postData)
	    ));
	    $jsonResponse      = curl_exec($ch);
	    $responseParamList = json_decode($jsonResponse, true);
	    return $responseParamList;
	}
	
	
    
}
