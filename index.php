<?php
/**
 * Created by: Alex Christian
 * Github: https://github.com/acqrdeveloper/renameFilesInDirectories
 */

define('BASE_PATH', 'grabaciones/');

//Inicializar funcion
explorerDirectorys();

//Explorar directorios existentes
function explorerDirectorys()
{
    $subDirectorys = scandir(BASE_PATH);
    foreach($subDirectorys as $subdirectory){
        if(!in_array($subdirectory, ['.', '..'])){
            $dirYears = scandir(BASE_PATH . '/' . $subdirectory);
            foreach($dirYears as $dirYear){
                if(!in_array($dirYear, ['.', '..'])){
                    $dirMonths = scandir(BASE_PATH . $subdirectory . '/' . $dirYear);
                    foreach($dirMonths as $dirMonth){
                        if(!in_array($dirMonth, ['.', '..'])){
                            $dirDays = scandir(BASE_PATH . $subdirectory . '/' . $dirYear . '/' . $dirMonth);
                            foreach($dirDays as $dirDay){
                                if(!in_array($dirDay, ['.', '..'])){
                                    $params = ['year' => $dirYear, 'month' => $dirMonth, 'day' => $dirDay, 'type' => $subdirectory];
                                    validateDir($params);
                                }
                            }//Carpeta dia
                        }
                    }//Carpeta mes
                }
            }//Carpeta Año
        }
    }//Sub carpeta
}

//Validar el directorio
function validateDir($params)
{
    switch($params['type']){
        case 'entrantes':
            cicloCdr($params);
            break;
        default:
            exit();
    }
}

//Obtener conexion PDO
function getConnection()
{
    $options = [
        PDO::ATTR_EMULATE_PREPARES => false, // turn off emulation mode for "real" prepared statements
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
    ];
    try{
        //Copiar conexion del .env
        return new PDO('','','',$options);
    }catch(PDOException $e){
        return $e->getMessage();
    }
}

//Obtener consulta de la base de datos SQL
function getCdr($params)
{
    try{
        $pdo = getConnection();
        //Query Auna
        if($params['type'] == 'entrantes'){
            $sql = "SELECT uniqueid, calldate, lastapp, disposition FROM cdr
            WHERE
            lastapp = 'Queue' 
            AND disposition = 'ANSWERED' 
            AND uniqueid != ''
            AND YEAR(calldate) = ?
            AND MONTH(calldate) = ?
            AND DAY(calldate) = ? ";
        }else{
            $sql = "SELECT uniqueid, calldate, lastapp, disposition FROM cdr
            WHERE
            lastapp = 'Dial' 
            AND disposition = 'ANSWERED' 
            AND uniqueid != ''
            AND YEAR(calldate) = ?
            AND MONTH(calldate) = ?
            AND DAY(calldate) = ? ";
        }
        /*$sql = "SELECT uniqueid, calldate, lastapp, disposition FROM cdr
            WHERE
            lastapp = 'Queue' 
            AND disposition = 'ANSWERED' 
            AND uniqueid != ''
            AND YEAR(calldate) = ?
            AND MONTH(calldate) = ?
            AND DAY(calldate) = ? ";*/
        //Query Helpdesk
        /*$sql = "SELECT uniqueid,calldate FROM cdr
                WHERE
                lastapp = 'Queue'
                AND YEAR(calldate) = ?
                AND MONTH(calldate) = ?
                AND DAY(calldate) between ? and ?";*/
        $data = $pdo->prepare($sql);
        $data->execute([$params['year'], $params['month'], $params['day']]);
        return $data->fetchAll();
    }catch(Exception $e){
        return $e->getMessage();
    }
}

//Hacer el ciclo en la data de CDR
function cicloCdr($params)
{
    $dataCdr = getCdr($params);
//    print_r($dataCdr);
//    exit();
    $finalRows = count($dataCdr);
    foreach($dataCdr as $k => $v){
        $request = null;
        $arrayConfig = explode('.', $v['uniqueid']);
        $unixtime = $arrayConfig[0];
        $anexo = $arrayConfig[1];
        $gsmTemp = $unixtime . '-' . $anexo . '.gsm';
        $newDatetime = new DateTime($v['calldate']);
        $request = ['dirYear' => $newDatetime->format('Y'),
            'dirMonth' => $newDatetime->format('m'),
            'dirDay' => $newDatetime->format('d'),
            'unixtime' => $unixtime,
            'anexo' => $anexo,
            'date' => $newDatetime->format('Ymd'),
            'hour' => $newDatetime->format('His'),
            'subDirectory' => $params['type']];
        enterDirectory($request, $gsmTemp, $finalRows, $k);
    }
}

//Acceder al directorio del archivo (.gsm)
function enterDirectory($request = [], $temp = null, $finalRows, $finalRow)
{
    try{
        $subDirectory = $request['subDirectory'];
        $dirYear = $request['dirYear'];
        $dirMonth = $request['dirMonth'];
        $dirDay = $request['dirDay'];
        $fullPath = BASE_PATH . $subDirectory . '/' . $dirYear . '/' . $dirMonth . '/' . $dirDay;
        $filesInDirectory = scandir($fullPath);
        foreach($filesInDirectory as $k => $v){
            if(!in_array($v, ['.', '..', 'por_verificar'])){
                //Nota: aqui agregar validacion, para ser renombrado
                if($v == $temp){//Validamos que el archivo exista, (example.gsm == example.gsm)
                    $newRename = $fullPath . '/' . $request['unixtime'] . '-' . $request['anexo'] . '-' . $request['date'] . '-' . $request['hour'] . '.gsm';
                    renameFile($fullPath . '/' . $v, $newRename);
                    $msg_log = 'modified ' . $v . ' to ' . $newRename . " \n";
                    createLog($fullPath . '/log.txt', $msg_log);
                    echo $msg_log;
                }
            }
        }
        //Correr al final
        if($finalRows - 1 == $finalRow){
            //Mover los archivos perdidos
            foreach($filesInDirectory as $kk => $vv){
                if(!in_array($vv, ['.', '..', 'por_verificar','log.txt'])){
                    $arrayFilesTemp = explode('-', $vv);
                    if(count($arrayFilesTemp) <= 2){
                        if(file_exists($fullPath . '/' . $vv)){
                            $newRename = $fullPath . '/por_verificar/' . $vv;
                            if(file_exists($fullPath . '/por_verificar')){
                                renameFile($fullPath . '/' . $vv, $newRename);
                            }else{
                                mkdir($fullPath . '/por_verificar');
                                renameFile($fullPath . '/' . $vv, $newRename);
                            }
                            $msg_log = 'moved ' . $vv . ' to ' . $newRename . " \n";
                            createLog($fullPath . '/log.txt', $msg_log);
                            echo $msg_log;
                        }
                    }
                }
            }
        }
    }catch(Exception $e){
        echo $e->getMessage();
    }
}

//Renombrar el archivo
function renameFile($file, $renameFile)
{
    rename($file, $renameFile);
}

//Validar y crear un archivo log por carpeta de trabajo
function createLog($path, $text)
{
    if(file_exists($path)){
        processLog($path, $text);
    }else{
        fopen($path, 'w') or die('Cannot open file:  ' . $path);
        processLog($path, $text);
    }
}

//Realizar el proceso de escritura en el archivo log
function processLog($path, $text)
{
    $data = file_get_contents($path);
    $data .= $text;
    file_put_contents($path, $data . "\r\n");
}