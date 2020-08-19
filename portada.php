<?php
  require_once "./vendor/autoload.php";
  include("db.php");

  $loader = new \Twig\Loader\FilesystemLoader('TW');
  $twig = new \Twig\Environment($loader);

  $error = 0; /*Variable para saber si hemos vuelto a la página de inicio por un error*/
  $notFound = 0; /*Variable para saber si hemos vuelto a la página por introducir en la URL una receta inexistente*/
  $correctLogin = 0; /*Nos indica si acabamos de ralizar el login de forma correcta o no, para llevarlo a la tabla de logging*/
  $endSession = 0;
  $wrongAccess = 0;
  $userData = null;
  $dbDeleted = 0;
  $fileUploaded = 0;
  $myOwnUserCreated = 0;

  if(isset($_GET['error'])){
    $error = 1;
  }

  if(isset($_GET['notFound'])){
    $notFound = 1;
  }

  if(isset($_GET['dbDeleted'])){
    $dbDeleted = 1;
  }

  if(isset($_GET['fileUploaded'])){
    $fileUploaded = 1;
  }

  if(isset($_GET['myOwnUserCreated'])){
    $myOwnUserCreated = 1;
  }

  if(isset($_GET['downloadPDF'])){  /*Descargamos la documentación*/
    header("Content-disposition: attachment; filename=documentacionSergiRuiz.pdf");
    header("Content-type: MIME");
    /*Leemos el archivo para mostrarlo en el navegador*/
    readfile("documentacionSergiRuiz.pdf");
  }


   /*------------------------------------------------------ CONTROL DE LOGIN -----------------------------------------------------------*/
   $login = 0; /*Variable que nos indica si el usuario está logeado o no*/
   $filename = $_SERVER['PHP_SELF']; /*Indicamos el nombre del fichero actual, de forma que cuando nos logueemos nos reenvíe a la misma página*/
   $usernameError = "";  /*Para indicar el error si hemos introducido el usuario o la contraseña mal*/
   $passwordError = "";
   $loginError = [];  /*Para controlar errores en el logueo, devolviendo mensajes*/
 
   session_start();  /*Iniciamos sesión*/
 
   if($_SERVER["REQUEST_METHOD"] == "POST"){
     if(isset($_POST['login'])){ /*Si hemos pulsado en el botón de logueo*/
      
      if(!empty($_POST['user'])){  /*Hemos introducido un nombre de usuario*/
        if(!empty($_POST['password'])){  /*Y hemos introducido una contraseña*/
          if(checkLogin($_POST['user'],$_POST['password']) == 1){  /*Datos correctos; introducimos los datos en las variables de sesión*/ 
            $_SESSION['user'] = $_POST['user'];
            $_SESSION['password'] = $_POST['password'];

            $userData = getUserData($_SESSION['user']);
            $_SESSION['userID'] = $userData['id'];

            $correctLogin = 1;
          }

          else{ /*Hemos introducido usuario y contraseña, pero alguno de los datos ha fallado*/
            if(userExists($_POST['user'],null) == 1){  /*Si el usuario existe, el problema es la contraseña*/
              $passwordError = "Contraseña incorrecta";
              $wrongAccess = 1;
            }

            else{ /*Error en el nombre de usuario*/
              $usernameError = "Usuario incorrecto";
            }

          }
        }

        else{ /*No hemos introducido contraseña*/
          $passwordError = "Introduce una contraseña";

        }

      }

      else{ /*No hemos introducido usuario*/
        $usernameError = "Introduce un usuario";
      }

      $loginError['user'] = $usernameError;
      $loginError['password'] = $passwordError;
 
     }

     if(isset($_POST['unlogin'])){/*Si hemos pulsado el botón de desloguear*/
       if(session_status()==PHP_SESSION_NONE){
           session_start();
       }

       /*Obtenemos los datos del usuario conectado*/
       $userData = getUserDataByID($_SESSION['userID']);
       $endSession = 1;
 
       
       session_unset();
       $cookieParams= session_get_cookie_params(); 
       
       setcookie(session_name(), $_COOKIE[session_name()], time()-2592000, $cookieParams['path'], 
       $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
       
       session_destroy();
     } 
   }
 
   if(isset($_SESSION['user']) && isset($_SESSION['password'])){
     $login = 1;
     $userData = getUserData($_SESSION['user']);
     
   }

   if($correctLogin == 1){  /*Acabamos de entrar al sistema*/
    $message = "El ".$userData['tipo']." ".$userData['nombre']." ".$userData['apellidos']." acaba de acceder al sistema";
    insertIntoLog($message);
  }
  
  if($endSession == 1){  /*Acabamos de cerrar la sesión*/
    $message = "El ".$userData['tipo']." ".$userData['nombre']." ".$userData['apellidos']." acaba de salir del sistema";
    insertIntoLog($message);
    $userData = null;
  }
  
  if($wrongAccess == 1){  /*Intento de acceso erróneo al sistema*/
    $message = "El usuario ".$_POST['user']." ha intenado acceder erróneamente al sistema";
    insertIntoLog($message);
  }

  if(isset($_GET['backup'])){ /*Si el admin ha pulsado en el enlace para realizar un backup*/
    if($_GET['backup'] == 1){
      if($userData['tipo'] == "admin"){ /*Solo se realiza si el usuario es de tipo administrador*/
        /*Primero, comprobaremos que el directorio de backups se encuentra vacío; en el caso de que no, eliminamos el backup anterior que haya en el*/
        if(is_dir("backup/")){
          $folder = scandir("backup/");
          
          if(count($folder) > 2){  /*Tiene dentro algo más que la referencia al propio directorio y a su padre*/
            $files = glob("backup/*");  /*obtenemos el nombre de los ficheros que haya dentro (backup, pero lo haremos así por si por algún casual hay alguno más)*/
            
            foreach($files as &$file){
              if($file == "backup/backupDB.txt"){ /*Si es el .txt del backup, lo borramos*/
                unlink($file);
              }
            }

          }
          /*Ya hemos eliminado el fichero o no lo hemos encontrado, por lo que pasamos a crear el fichero backup.txt, lo escribimos y guardamos en el directorio*/
          $textFile = backup();

          /*Aquí estamos machacando el fichero anterior, por lo que no sería necesario borrarlo como hemos hecho arriba, pero para evitar posibles errores lo hacemos*/
          $backupFile = fopen("backup/backupDB.txt","w"); 
          fwrite($backupFile,$textFile);
          fclose($backupFile);

          /*Una vez ya lo tenemos almacenado, procedemos a su descarga (establecemos las cabeceras de php necesarias)*/
          header("Content-disposition: attachment; filename=backupBD.txt");
          header("Content-type: MIME");
          /*Leemos el archivo para mostrarlo en el navegador*/
          readfile("backup/backupDB.txt");

        }
      }
    }
  }


  /*--------------------------------- OBTENEMOS EL TOTAL DE LAS RECETAS -----------------------------------------*/
  $totalRecipes = getTotalRecipes();
  $mostCommented = getMostCommented();


  echo $twig->render('portada.html', ['totalRecipes'=>$totalRecipes,'login'=>$login,'filename'=>$filename,'loginError'=>$loginError,'error'=>$error,'notFound'=>$notFound,
  'userData'=>$userData,'mostCommented'=>$mostCommented,'dbDeleted'=>$dbDeleted,'fileUploaded'=>$fileUploaded,'myOwnUserCreated'=>$myOwnUserCreated]);


?>