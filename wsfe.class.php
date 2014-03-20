<?php

class WSFE {

  const CUIT = "30708235284";                 # CUIT del emisor de las facturas
  const TA =    "xmlgenerados/TA.xml";        # Archivo con el Token y Sign
  const WSDL = "wsfe.wsdl";                   # The WSDL corresponding to WSFE
  const CERT = "keys/ghf.crt";                # The X.509 certificate in PEM format
  const PRIVATEKEY = "keys/ghf.key";          # The private key correspoding to CERT (PEM)
  const PASSPHRASE = "";                      # The passphrase (if any) to sign
  const PROXY_ENABLE = false;
  const LOG_XMLS = false;                     # For debugging purposes
  const WSFEURL = "https://wswhomo.afip.gov.ar/wsfe/service.asmx"; // testing
  //const WSFEURL = "?????????????????"; // produccion  

  
  /*
   * el path relativo, terminado en /
   */
  private $path = './';
  
  /*
   * manejo de errores
   */
  public $error = '';
  
  /**
   * Cliente SOAP
   */
  private $client;
  
  /**
   * objeto que va a contener el xml de TA
   */
  private $TA;
  
  /**
   * tipo_cbte defije si es factura A = 1 o B = 6
   */
  private $tipo_cbte = '1';
  
  /*
   * Constructor
   */
  public function __construct($path = './') 
  {
    $this->path = $path;
    
    // seteos en php
    ini_set("soap.wsdl_cache_enabled", "0");    
    
    // validar archivos necesarios
    if (!file_exists($this->path.self::WSDL)) $this->error .= " Failed to open ".self::WSDL;
    
    if(!empty($this->error)) {
      throw new Exception('WSFE class. Faltan archivos necesarios para el funcionamiento');
    }        
    
    $this->client = new SoapClient($this->path.self::WSDL, array( 
              'soap_version' => SOAP_1_2,
              'location'     => self::WSFEURL,
              'exceptions'   => 0,
              'trace'        => 1)
    ); 
  }
  
  /**
   * Chequea los errores en la operacion, si encuentra algun error falta lanza una exepcion
   * si encuentra un error no fatal, loguea lo que paso en $this->error
   */
  private function _checkErrors($results, $method)
  {
    if (self::LOG_XMLS) {
      file_put_contents("xmlgenerados/request-".$method.".xml",$this->client->__getLastRequest());
      file_put_contents("xmlgenerados/response-".$method.".xml",$this->client->__getLastResponse());
    }
    
    if (is_soap_fault($results)) {
      throw new Exception('WSFE class. FaultString: ' . $results->faultcode.' '.$results->faultstring);
    }
    
    if ($method == 'FEDummy') {return;}
    
    $XXX=$method.'Result';
    if ($results->$XXX->RError->percode != 0) {
        $this->error = "Method=$method errcode=".$results->$XXX->RError->percode." errmsg=".$results->$XXX->RError->perrmsg;
    }
    
    return $results->$XXX->RError->percode != 0 ? true : false;
  }

  /**
   * Abre el archivo de TA xml,
   * si hay algun problema devuelve false
   */
  public function openTA()
  {
    $this->TA = simplexml_load_file($this->path.self::TA);
    
    return $this->TA == false ? false : true;
  }
  
  /**
   * Retorna la cantidad maxima de registros de detalle que 
   * puede tener una invocacion al FEAutorizarRequest
   */
  public function recuperaQTY()
  {
    $results = $this->client->FERecuperaQTYRequest(
      array('argAuth'=>array('Token' => $this->TA->credentials->token,
                              'Sign' => $this->TA->credentials->sign,
                              'cuit' => self::CUIT)));
    
    $e = $this->_checkErrors($results, 'FERecuperaQTYRequest');
        
    return $e == false ? $results->FERecuperaQTYRequestResult->qty->value : false;
  }

  /*
   * Retorna el ultimo número de Request.
   */ 
  public function ultNro()
  {
    $results = $this->client->FEUltNroRequest(
      array('argAuth'=>array('Token' => $this->TA->credentials->token,
                              'Sign' => $this->TA->credentials->sign,
                              'cuit' => self::CUIT)));
    
    $e = $this->_checkErrors($results, 'FEUltNroRequest');
        
    return $e == false ? $results->FEUltNroRequestResult->nro->value : false;
  }
  
  /*
   * Retorna el ultimo comprobante autorizado para el tipo de comprobante /cuit / punto de venta ingresado.
   */ 
  public function recuperaLastCMP ($ptovta)
  {
    $results = $this->client->FERecuperaLastCMPRequest(
      array('argAuth' =>  array('Token'    => $this->TA->credentials->token,
                                'Sign'     => $this->TA->credentials->sign,
                                'cuit'     => self::CUIT),
             'argTCMP' => array('PtoVta'   => $ptovta,
                                'TipoCbte' => $this->tipo_cbte)));
                                
    $e = $this->_checkErrors($results, 'FERecuperaLastCMPRequest');
    
    return $e == false ? $results->FERecuperaLastCMPRequestResult->cbte_nro : false;
  }
  
  /*
   * Obtiene los tipos de Documentos
   */
  public function getTiposDoc()
  {
    $params->Auth->Token = $this->TA->credentials->token;
    $params->Auth->Sign = $this->TA->credentials->sign;
    $params->Auth->Cuit = self::CUIT;
    $results = $this->client->FEParamGetTiposDoc($params);
    
    //this->_checkErrors($results, 'FEParamGetTiposDoc');
    
    $X=$results->FEParamGetTiposDocResult;
    //$fh=fopen("TiposDoc.txt","w");
    foreach ($X->ResultGet->DocTipo AS $Y) {
      //fwrite($fh,sprintf("%5s %-30s\n",$Y->Id, $Y->Desc));
      echo $Y->Id .' '.$Y->Desc;
    }
    //fclose($fh);
  }
  
  /**
   * Setea el tipo de comprobante
   * A = 1
   * B = 6
   */
  public function setTipoCbte($tipo) 
  {
    switch($tipo) {
      case 'a': case 'A': case '1':
        $this->tipo_cbte = 1;
      break;
      
      case 'b': case 'B': case 'c': case 'C': case '6':
        $this->tipo_cbte = 6;
      break;
      
      default:
        return false;
    }

    return true;
  }

  // Dado un lote de comprobantes retorna el mismo autorizado con el CAE otorgado.
  public function aut($ID, $cbte, $ptovta, $regfac)
  {
    $results = $this->client->FEAutRequest(
      array('argAuth' => array(
               'Token' => $this->TA->credentials->token,
               'Sign'  => $this->TA->credentials->sign,
               'cuit'  => self::CUIT),
            'Fer' => array(
               'Fecr' => array(
                  'id' => $ID, 
                  'cantidadreg' => 1, 
                  'presta_serv' => 0
                ),
               'Fedr' => array(
                  'FEDetalleRequest' => array(
                     'tipo_doc' => $regfac['tipo_doc'],
                     'nro_doc' => $regfac['nro_doc'],
                     'tipo_cbte' => $this->tipo_cbte,
                     'punto_vta' => $ptovta,
                     'cbt_desde' => $cbte,
                     'cbt_hasta' => $cbte,
                     'imp_total' => $regfac['imp_total'],
                     'imp_tot_conc' => $regfac['imp_tot_conc'],
                     'imp_neto' => $regfac['imp_neto'],
                     'impto_liq' => $regfac['impto_liq'],
                     'impto_liq_rni' => $regfac['impto_liq_rni'],
                     'imp_op_ex' => $regfac['imp_op_ex'],
                     'fecha_cbte' => date('Ymd'),
                     'fecha_venc_pago' => $regfac['fecha_venc_pago']
                   )
                )
              )
       )
     );
    
    $e = $this->_checkErrors($results, 'FEAutRequest');
        
    return $e == false ? Array( 'cae' => $results->FEAutRequestResult->FedResp->FEDetalleResponse->cae, 'fecha_vencimiento' => $results->FEAutRequestResult->FedResp->FEDetalleResponse->fecha_vto ): false;
  }

} // class

?>
