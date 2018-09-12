<?php
/**
 * Created by: Alex Christian
 * Github: https://github.com/acqrdeveloper/renameFilesInDirectories
 */

//ini_set('memory_limit','256M');
define('BASE_PATH', 'grabaciones/');
//define('BASE_PATH', '/mnt/audios/');

//Inicializar funcion
explorerDirectorys();

//Explorar directorios existentes
function explorerDirectorys()
{
    try{
        $subDirectorys = scandir(BASE_PATH);
//            var_dump($subDirectorys);
        foreach($subDirectorys as $subdirectory){
            if(!in_array($subdirectory, ['.', '..', 'salientes'])){//Aqui ignoramos carpetas
                $dirYears = scandir(BASE_PATH . '/' . $subdirectory);
                foreach($dirYears as $dirYear){
                    if(!in_array($dirYear, ['.', '..', '2016','2018'])){//Aqui ignoramos carpetas
                        $dirMonths = scandir(BASE_PATH . $subdirectory . '/' . $dirYear);
                        foreach($dirMonths as $dirMonth){
                            if(!in_array($dirMonth, ['.', '..'])){
                                $dirDays = scandir(BASE_PATH . $subdirectory . '/' . $dirYear . '/' . $dirMonth);
                                foreach($dirDays as $dirDay){
                                    if(!in_array($dirDay, ['.', '..'])){
                                        $params = ['year' => $dirYear, 'month' => $dirMonth, 'day' => $dirDay, 'type' => $subdirectory];
                                        validateDir($params);
                                    }else{
                                        echo "saliste del dia ".$dirDay." \n";
                                    }
                                }//Carpeta dia
                            }else{
                                echo "saliste del mes ".$dirMonth." \n";
                            }
                        }//Carpeta mes
                    }else{
                        echo "saliste del año ".$dirYear." \n";
                    }
                }//Carpeta Año
            }else{
                echo "saliste del directorio ".$subdirectory." \n";
            }
        }//Sub carpeta
    }catch(Exception $e){
        echo $e->getMessage();
    }
}

//Validar el directorio
function validateDir($params)
{
    switch($params['type']){
        case 'entrantes':
            cicloCdr($params);
            break;
        case 'salientes':
            cicloCdr($params);
            break;
        default:
            exit();
    }
}

//Select
function select($sql, $params = [])
{
    try{
        $pdo = getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }catch(Exception $e){
        echo $e->getMessage();
    }
}

//Obtener conexion PDO
function getConnection()
{
    try{
        $options = [
            PDO::ATTR_EMULATE_PREPARES => false, // turn off emulation mode for "real" prepared statements
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
        ];

        $driver = '{MsSQL}';
        $hostname = 'cluster_sql.sapia.pe,2054';
        $database = 'auna';
        $username = 'temporal';
        $password = 'temporal';
        $dsnSqlServer = "odbc:Driver=$driver;Server=$hostname;Database=$database";
        return new PDO($dsnSqlServer, $username, $password, $options);

        // $driver = 'mysql';
        // $hostname = '10.151.112.16';
        // $database = 'asteriskcdrdb';
        // $username = 'sistemas';
        // $password = 'sistemas';
        // $dsnMysql = "$driver:host=$hostname;dbname=$database";
        // return new PDO($dsnMysql,$username,$password,$options);

    }catch(PDOException $e){
        echo $e->getMessage();
    }
}

//Obtener consulta de la base de datos SQL
function getDataBD($params)
{
    try{
        /*if($params['type'] == 'entrantes'){//Entrantes
            $sql = "SELECT uniqueid, calldate, lastapp, disposition, src, dst FROM cdr
            WHERE
            lastapp = 'Queue' 
            AND disposition = 'ANSWERED' 
            -- AND uniqueid != ''
            AND YEAR(calldate) = ?
            AND MONTH(calldate) = ?
            AND DAY(calldate) = ? ";
        }else{//Salientes
            $sql = "SELECT uniqueid, calldate, lastapp, disposition, src, dst FROM cdr
            WHERE
            lastapp = 'Dial' 
            AND disposition = 'ANSWERED' 
            -- AND uniqueid != ''
            AND YEAR(calldate) = ?
            AND MONTH(calldate) = ?
            AND DAY(calldate) = ? ";
        }*/

        $sql = "select s.clid, q.vdn, s.datetime, s.uniqueid, s.queue
                from queue_stats_mv s
                join queues q on q.name = s.queue 
                where 
                YEAR(s.datetime) = ? AND
                MONTH(s.datetime) = ? AND
                DAY(s.datetime) = ? ";

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
//        $data = $pdo->prepare($sql);
//        $data->execute([$params['year'], $params['month'], $params['day']]);
//        return $data->fetchAll();

        var_dump($params);
        return select($sql, [$params['year'], $params['month'], $params['day']]);
    }catch(Exception $e){
        echo $e->getMessage();
    }
}

function debug($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    exit();
}

//Hacer el ciclo en la data de CDR
function cicloCdr($params)
{
//    $dataBD = getDataBD($params);
    $dataBD = select("select * from users");
    debug($dataBD);
    $finalRows = count($dataBD);
    foreach($dataBD as $k => $v){
        $request = null;
        $arrayConfig = explode('.', $v['uniqueid']);
        $unixtime = $arrayConfig[0];
        $anexo = $arrayConfig[1];
        $newDatetime = new DateTime($v['datetime']);
        if($params['type'] == 'entrantes'){//Carpeta Entrantes
            $gsmTemp = $unixtime . '-' . $anexo . '.gsm';
        }else{//Carpeta Salientes
            $gsmTemp = $newDatetime->format('Ymd') . '-' . $newDatetime->format('His') . '-' . $v['src'] . '-' . $v['dst'] . '.gsm';
        }
        $request = ['dirYear' => $newDatetime->format('Y'),
            'dirMonth' => $newDatetime->format('m'),
            'dirDay' => $newDatetime->format('d'),
            'unixtime' => $unixtime,
            'anexo' => $anexo,
            'date' => $newDatetime->format('dmY'),
            'time' => $newDatetime->format('His'),
            'clid' => $v['clid'],
            'vdn' => $v['vdn'],
            'queue' => $v['queue'],
            'subDirectory' => $params['type']];
        enterDirectory($request, $gsmTemp, $finalRows, $k);
    }
}

//Acceder al directorio del archivo (.gsm)
function enterDirectory($request = [], $gsmTemp = null, $finalRows, $finalRow)
{
    try{
        $subDirectory = $request['subDirectory'];
        $dirYear = $request['dirYear'];
        $dirMonth = $request['dirMonth'];
        $dirDay = $request['dirDay'];
        $fullPath = BASE_PATH . $subDirectory . '/' . $dirYear . '/' . $dirMonth . '/' . $dirDay . '/';
        $mediaPath = '/' . $dirYear . '/' . $dirMonth . '/' . $dirDay . '/';
        $filesInDirectory = scandir($fullPath);
        foreach($filesInDirectory as $k => $v){
            if(!in_array($v, ['.', '..', 'por_verificar'])){
                //Nota: aqui agregar validacion, para ser renombrado
                if($v == $gsmTemp){//Validamos que el archivo exista, (example.gsm == example.gsm)
                    $pathCreate = '';
                    $pathGsm = '';
                    if($subDirectory == 'entrantes'){//Carpeta Entrantes
                        $pathGsm = $request['clid'] . '-' . $request['vdn'] . '-' . $request['date'] . '-' . $request['time'] . '.gsm';
                        $newRename = $fullPath . $pathGsm;
//                        $mediaPath = '/' . $dirYear . '/' . $dirMonth . '/' . $dirDay;
                        $pathCreate = BASE_PATH . $request['queue'] . $mediaPath;
                        // $newRename = $fullPath . '/' . $request['dst'] . '-' . $request['anexo'] . '-' . $request['date'] . '-' . $request['hour'] . '.gsm';
                    }else{//Carpeta Salientes
                        $newRename = $fullPath . $request['src'] . '-' . $request['dst'] . '-' . $request['date'] . '-' . $request['time'] . '.gsm';
                    }
                    //
                    //Proceso del Archivo
                    //
                    rename($fullPath . $v, $newRename);//Archivo renombrado
                    $msg_log = 'modified ' . $v . ' to ' . $newRename . " \n";//Mensaje del Log
                    createLog($fullPath . '/log.txt', $msg_log);//Creamos el log
                    echo $msg_log;
                    moveFile($newRename, $pathCreate . $pathGsm, $pathCreate);//Archivo movido
                    $msg_log = 'moved ' . $newRename . ' to ' . $pathCreate . $pathGsm . " \n";
                    createLog($fullPath . '/log.txt', $msg_log);//Creamos el log
                    echo $msg_log;
                }
            }
        }
        //Correr al final
        if($finalRows - 1 == $finalRow){
            //Mover los archivos perdidos
            foreach($filesInDirectory as $kk => $vv){
                if(!in_array($vv, ['.', '..', 'por_verificar', 'log.txt'])){
                    $arrayFileTemp = explode('-', $vv);
                    if($subDirectory == 'entrantes'){//Carpeta Entrantes
                        if(count($arrayFileTemp) <= 2){
                            if(file_exists($fullPath . '/' . $vv)){
                                $newRename = $fullPath . '/por_verificar/' . $vv;
                                if(file_exists($fullPath . '/por_verificar')){
                                    rename($fullPath . '/' . $vv, $newRename);
                                }else{
                                    mkdir($fullPath . '/por_verificar');
                                    rename($fullPath . '/' . $vv, $newRename);
                                }
                                $msg_log = 'moved ' . $vv . ' to ' . $newRename . " \n";
                                createLog($fullPath . '/log.txt', $msg_log);
                                echo $msg_log;
                            }
                        }
                    }else{//Carpeta Salientes
                        if($vv == $gsmTemp){
                            if(file_exists($fullPath . '/' . $vv)){
                                $newRename = $fullPath . '/por_verificar/' . $vv;
                                if(file_exists($fullPath . '/por_verificar')){
                                    rename($fullPath . '/' . $vv, $newRename);
                                }else{
                                    mkdir($fullPath . '/por_verificar');
                                    rename($fullPath . '/' . $vv, $newRename);
                                }
                                $msg_log = 'moved ' . $vv . ' to ' . $newRename . " \n";
                                createLog($fullPath . '/log.txt', $msg_log);
                                echo $msg_log;
                            }
                        }
                    }
                }
            }
        }
    }catch(Exception $e){
        echo $e->getMessage();
    }
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

function moveFile($oldPath, $newPath, $createPath)
{
    echo $createPath . "\n";
    if(file_exists($createPath)){
        rename($oldPath, $newPath);
    }else{
        $folders = explode('/', $createPath);
        mkdir('./' . $folders[0] . '/' . $folders[1]);//Crear carpeta Cola
        mkdir('./' . $folders[0] . '/' . $folders[1] . '/' . $folders[2]);//Crear carpeta Año
        mkdir('./' . $folders[0] . '/' . $folders[1] . '/' . $folders[2] . '/' . $folders[3]);//Crear carpeta Mes
        mkdir('./' . $folders[0] . '/' . $folders[1] . '/' . $folders[2] . '/' . $folders[3] . '/' . $folders[4]);//Crear carpeta Dia
        rename($oldPath, $newPath);
    }
}