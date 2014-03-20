<?php

require 'exceptionhandler.php';
require 'wsaa.class.php';
require 'wsfe.class.php';

/**********************
 * Ejemplo WSAA
 * ********************/

$wsaa = new WSAA('./'); 


if($wsaa->get_expiration() < date("Y-m-d h:m:i")) {
  if ($wsaa->generar_TA()) {
    echo 'obtenido nuevo TA';  
  } else {
    echo 'error al obtener el TA';
  }
} else {
  echo $wsaa->get_expiration();
};



/**********************
 * Ejemplo WSFE
 * ********************
 */

$wsfe = new WSFE('./');
 
 
// Carga el archivo TA.xml
$wsfe->openTA();

//$wsfe->getTiposDoc();
 
  
// devuelve el cae
$ptovta = 1; 
$tipocbte = 1;
                   
// registro con los datos de la factura
$regfac['tipo_doc'] = 80;
$regfac['nro_doc'] = 23111111112;
$regfac['imp_total'] = 121.67;
$regfac['imp_tot_conc'] = 0;
$regfac['imp_neto'] = 100.55;
$regfac['impto_liq'] = 21.12;
$regfac['impto_liq_rni'] = 0.0;
$regfac['imp_op_ex'] = 0.0;
$regfac['fecha_venc_pago'] = date('Ymd');

$nro = $wsfe->ultNro();
if($nro == false) echo "erorrrrrrr ultNro";

$cmp = $wsfe->recuperaLastCMP($ptovta, $tipocbte);
if($cmp == false) echo "erorrrrrrr cmppp";

$cae = $wsfe->aut($nro + 1, // ultimo ID mas uno 
                $cmp + 1, // ultimo numero de  comprobante autorizado mas uno 
                $ptovta,  // el punto de venta
                $regfac // los datos a facturar
     );
if($cae == false) echo "erorrrrrrr Caeee";

print_r($cae);

?>