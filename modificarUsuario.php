<?php
  require_once "./vendor/autoload.php";
  include("db.php");

  $loader = new \Twig\Loader\FilesystemLoader('TW');
  $twig = new \Twig\Environment($loader);

  $formData = []; /*Donde vamos a almacenar todos los datos*/
  $userModified = 0; /*Nos indica si hemos modificado o no un usuario*/
  $endSession = 0;
  $userDeleted = 0;
  $userCreated = 0;
  $userUpdated = 0;
  $myOwnUserCreated = 0;
  $userData = null;

    /*------------------------------------------------------ CONTROL DE LOGIN -----------------------------------------------------------*/
  $login = 0; /*Variable que nos indica si el usuario está logeado o no*/

  session_start();  /*Iniciamos sesión*/

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

  if(isset($_SESSION['user']) && isset($_SESSION['password'])){
    $login = 1;
    $userData = getUserData($_SESSION['user']);
  }


  if($endSession == 1){  /*Acabamos de cerrar la sesión*/
    $message = "El ".$userData['tipo']." ".$userData['nombre']." ".$userData['apellidos']." acaba de salir del sistema";
    insertIntoLog($message);
    $userData = null;
    
  }

  if(($login==0 & !isset($_GET['createUser']) & !isset($_POST['createMyOwnUser']) & !isset($_GET['createMyOwnUser']) & !isset($_POST['checked'])) | 
    ($login== 1 & !isset($_GET['createUser']) & !isset($_POST['createMyOwnUser']) & !isset($_POST['editUser']) & !isset($_POST['delete']) & !isset($_POST['checked']))){
    header("Location:portada.php");
  }
  
  /*Si nos llega variable get, es porque vamos a crear usuario*/
  if(isset($_GET['createUser'])){
      $formData['createUser'] = 1;

      if(isset($_GET['createMyOwnUser'])){
        if($_GET['createMyOwnUser'] == 1){  /*El usuario va a crearse su propio usuario*/
          $formData['createMyOwnUser'] = 1;
        }
      }

  }

  /*Si recibimos la variable, es porque acabamos de llegar, ya sea porque el admin quiere modificar los datos de algún usuario o el usuario quiere modificar sus propios datos*/
  if(isset($_POST['editUser'])){
      if(isset($_POST['myOwnData'])){   /*El usuario va a modificar sus propios datos*/
        $formDataAux = getUserData($_SESSION['user']); /*Obtenemos nuestros propios datos*/
        $formData['myOwnData'] = 1;
        $formData['tipo'] = $userData['tipo'];


        /*Borramos las entradas que ya hubiere en el directorio de imagenes temporal*/
        $totalFiles = glob("tmpUsers/*");

        foreach($totalFiles as &$singleFile){
          if($singleFile != "." & $singleFile != ".."){
            unlink($singleFile);
          }
        }

        /*Obtenemos el nombre de la imagen*/
        $photoOriginalName = $userData['foto'];       

        /*movemos la foto de ese usuario al directorio temporal, para poder modificarla sin cambiar la original, por si decide deshacer los cambios*/
        copy("usersProfiles/".$photoOriginalName,"tmpUsers/".$photoOriginalName);

      }

      else{
        $formDataAux = getUserData($_POST['modifyUserName']); /*Obtenemos los datos del usuario a modificar*/
        $formData['modifyUser'] = 1;
        $formData['myOwnData'] = 0;
      }

        $formData['userName'] = $formDataAux['nombre'];
        $formData['userSurname'] = $formDataAux['apellidos'];
        $formData['userEmail'] = $formDataAux['email'];
        $formData['checked'] = 0;
        $formData['tipo'] = $formDataAux['tipo'];
        $formData['foto'] = $formDataAux['foto'];

  }

  if(isset($_POST['checked'])){

      if($_POST['checked'] == 0){   /*Aún tenemos error en algún campo*/
        /*Primero, rellenamos los campos que no cambian*/
        $formData['checked'] = 0;
        $formData['myOwnData'] = $_POST['myOwnData'];

        if(isset($_POST['createUser'])){
          $formData['createUser'] = $_POST['createUser'];
        }

        if(isset($_POST['createMyOwnUser'])){
          $formData['createMyOwnUser'] = $_POST['createMyOwnUser'];

        }

        /*Segundo, creamos las variables relacionadas con los campos de texto*/
        $userNameError = $userSurnameError = $userEmailError = $userPasswordError = "";
        $userName = $userSurname = $userEmail = $userPassword = "";

            /*Datos relacionados con nombre y apellidos*/
            if(empty($_POST['userName'])){ /*No recibimos nombre*/
                $userNameError = "Introduce un nombre de usuario";
            }

            else{   /*Lo ha introducido*/
                $userName = test_input($_POST['userName']);
            }

            if(empty($_POST['userSurname'])){ /*No recibimos apellido*/
                $userSurnameError = "Introduce los apellidos";
            }

            else{   /*Lo ha introducido*/
                $userSurname = test_input($_POST['userSurname']);
            }


            if($userName != "" & $userSurname != ""){   /*Tenemos valores en ambos campos*/
                /*Intentamos cambiarnos el bloque nombre-apellidos por uno ya cogido por otro usuario*/
                if($formData['myOwnData'] == 1){
                  if(userExists($userName,$userSurname) & ($userName != $userData['nombre'] | $userSurname != $userData['apellidos'])){ 
                    $userName = "";
                    $userSurname = "";
                    $userNameError = "No puede tener mismo nombre y apellidos que otro usuario";
                  }
                }

                if(isset($_POST['createUser'])){ /*El admin intenta creaur un user ya existente*/
                  if($formData['createUser'] == 1){
                    if(userExists($userName,$userSurname)){ 
                      $userName = "";
                      $userSurname = "";
                      $userNameError = "No puede tener mismo nombre y apellidos que otro usuario";
                    }
                  }
                }
            }
            

            /*Datos relacionados con el correo*/
            if(empty($_POST['userEmail'])){ /*No recibimos correo*/
                $userEmailError = "Introduce un correo";
            }

            else{   /*Lo ha introducido*/
                if(checkFormatEmail($_POST['userEmail'])){  /*Email en el formato correcto*/
                    $userEmail = ($_POST['userEmail']);
                }

                else{   /*No cumple con el formato*/
                    $userEmailError="Correo en un formato no válido";
                }
                
            }

            /*Datos relacionados con la contraseña*/
            if(empty($_POST['newPassword1']) & empty($_POST['newPassword2'])){  /*Si no introducimos ninguna, porque no queremos cambiarla*/
              if(isset($_POST['createUser'])){
                if($formData['createUser'] == 1){
                  $userPasswordError = "Debe introducir una contraseña nueva";
                }
              }
            }

             /*No escribimos el valor en alguna de las contraseñas*/
            else if((!empty($_POST['newPassword1']) & empty($_POST['newPassword2']))| (!empty($_POST['newPassword2']) & empty($_POST['newPassword1']))){ 

                $userPasswordError = "Las contraseñas no coinciden";
            }

            else if (!empty($_POST['newPassword1']) & !empty($_POST['newPassword2'])){    /*Escribimos ambas*/

                if(test_input($_POST['newPassword1']) == test_input($_POST['newPassword2'])){ /*Ambas coinciden*/
                    $userPassword = test_input($_POST['newPassword1']);
                    
                }

                else{   /*No coindicen*/
                    $userPasswordError = "Las contraseñas no coinciden";
                }
            }

            /*Datos relacionados con la imagen*/
            $updateImage = 0; /*Variable que nos va a indicar si hemos actualizado o no la imagen*/
            $userImageError = "";
            
            $photoOriginalName = $userData['foto'];

            if($_FILES['newUserImage']['size'] != 0){  /*Tiene tamaño, por lo que hay foto*/
              

              $file_name = $_FILES['newUserImage']['name'];
              $file_size = $_FILES['newUserImage']['size'];
              $file_tmp = $_FILES['newUserImage']['tmp_name'];
              $file_type = $_FILES['newUserImage']['type'];

              $file_ext = strtolower(end(explode('.',$file_name))); /*Obtenemos la extensión de la imagen*/
                 
              $extensions= array("jpg");  /*Para evitar problemas de nombres de las imágenes, solo permitiremos extensión jpg*/
              
              if (in_array($file_ext,$extensions) === false){
                $userImageError = "Extensión no permitida, elige una imagen JPG";
              }
              
              if ($file_size > 2097152){
                $userImageError = 'Tamaño del fichero demasiado grande';
              }
              
              if ($userImageError == "") {  /*No hemos tenido errores*/


                move_uploaded_file($file_tmp, "tmpUsers/".$file_name); /*Subimos la nueva foto al directorio*/
                rename("tmpUsers/".$file_name,"tmpUsers/".$photoOriginalName); /*Reemplazamos la nueva imagen por la antigua*/
                $updateImage = 1;
              }

          }

        
        if($userNameError == "" & $userSurnameError == "" & $userEmailError == "" & $userPasswordError == "" & $userImageError == ""){
            if($updateImage == 0){
              $formData['checked'] = 1;
            }
        }


        /*Asociamos los valores*/
        $formData['userName'] = $userName;
        $formData['userNameError'] = $userNameError;
        $formData['userSurname'] = $userSurname;
        $formData['userSurnameError'] = $userSurnameError;
        $formData['userEmail'] = $userEmail;
        $formData['userEmailError'] = $userEmailError;
        $formData['userPassword'] = $userPassword;
        $formData['userPasswordError'] = $userPasswordError;
        $formData['userImageError'] = $userImageError;
        $formData['foto'] = $_POST['userPhoto'];

        if(isset($_POST['userRol'])){
          $formData['tipo'] = $_POST['userRol'];
        }

        if(isset($_POST['userNameAdminUpdate']) & isset($_POST['userSurnameAdminUpdate']) & isset($_POST['userEmailAdminUpdate'])){ /*Variables para actualizar el usuario*/
          $formData['checked'] = 1;
          $formData['userName'] = $_POST['userNameAdminUpdate'];
          $formData['userSurname'] = $_POST['userSurnameAdminUpdate'];
          $formData['userEmail'] = $_POST['userEmailAdminUpdate'];
          $formData['tipo'] = $_POST['userRol'];
          $formData['modifyUser'] = $_POST['modifyUser'];
        }

      }

      else{ /*vamos a checkear que todo esté en orden antes de enviarlo*/
        if(isset($_POST['updateUserData'])){    /*Pulsamos en enviar*/
            if($_POST['myOwnData'] == 1){    /*Modificamos nuestros propios datos*/
                $password = trim($_POST['updateUserPassword']);

                /*checkeamos si existe entrada en el directorio de tmpUsers, para actualizar la foto de perfil*/
                $temporalPhoto = "tmpUsers/".$userData['foto'];
                
                if(file_exists($temporalPhoto)){  
                  rename($temporalPhoto,"usersProfiles/".$userData['foto']);  /*La movemos al directorio final con su nombre original*/
                }

                if($password != null){  /*Vamos a cambiar la contraseña*/
                    modifyUserData($userData['nombre'],$userData['apellidos'],$_POST['updateUserName'],$_POST['updateUserSurname'],$_POST['updateUserEmail'],$password);

                    $_SESSION['password'] = $password;
                }

                else{   /*Mantenemos la antigua*/

                    modifyUserData($userData['nombre'],$userData['apellidos'],$_POST['updateUserName'],$_POST['updateUserSurname'],$_POST['updateUserEmail'],null);
                }

                $_SESSION['user'] = $_POST['updateUserName'];
                $userModified = 1;


            }

            else{ /*Actualizamos datos de otro usuario, por lo que solo cambiamos los privilegios*/
              modifyPrivileges($userData['nombre'],$userData['apellidos'],$_POST['updateUserName'],$_POST['updateUserSurname'],$_POST['updateUserRol']);
              $userUpdated = 1;
            }
        }

        if(isset($_POST['deleteUserOK'])){  /*Vamos a eliminar a ese usuario*/
          deleteUser($userData['nombre'],$userData['apellidos'],$_POST['deleteUserOKName'],$_POST['deleteUserOKSurname']);
          $userDeleted = 1;

        }

        if(isset($_POST['createUserData'])){  /*Vamos a crear un usuario*/

          if(isset($_POST['createMyOwnUser'])){ /*Usuario creado por el propio usuario, así que le enviamos el correo*/
            createMyOwnUser($_POST['updateUserName'],$_POST['updateUserSurname'],$_POST['updateUserEmail'],$_POST['updateUserPassword']);
            $myOwnUserCreated = 1;
          }

          else{ /*Usuario creado por un admin, por lo que lo creamos directamente*/
            createUser($userData['nombre'],$userData['apellidos'],$_POST['updateUserName'],$_POST['updateUserSurname'],$_POST['updateUserEmail'],
            $_POST['updateUserPassword'],$_POST['updateUserRol']);

            $userCreated = 1;
          }

        }

      }

      
  }

  if(isset($_POST['delete'])){  /*Vamos a borrar a ese usuario*/
    $auxData = getUserData($_POST['deleteUserName'],$_POST['deleteUserSurname']); /*Si solo ponemos el nombre, eliminaría a todos los que lo comparten*/
    $formData['userName'] = $auxData['nombre'];
    $formData['userSurname'] = $auxData['apellidos'];
    $formData['userEmail'] = $auxData['email'];
    $formData['tipo'] = $auxData['tipo'];
    $formData['checked'] = 1;
    $formData['deleteUser'] = 1;
    $formData['foto'] = $auxData['foto'];

  }


  /*Función para validar los datos recibidos del formulario*/
  function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
  }

  /*Función para ver que el correo cumple con el formato necesario*/
  function checkFormatEmail($email){
    $correct = false;

    if(preg_match('/^[A-z0-9\\._-]+@[A-z0-9][A-z0-9-]*(\\.[A-z0-9_-]+)*\\.([A-z]{2,6})$/', $email)){
      $correct = true;
    }

    return $correct;
  }

  if($userModified == 1){
    header("Location:portada.php?userModified=1");
  }

  if($userDeleted == 1){
    header("Location:lista.php?users&userDeleted=1");
  }

  if($userCreated == 1){
    header("Location:lista.php?users&userCreated=1");
  }

  if($userUpdated == 1){
    header("Location:lista.php?users&userUpdated=1");
  }

  if( $myOwnUserCreated == 1){
    header("Location:portada.php?myOwnUserCreated=1");
  }

  $totalRecipes = getTotalRecipes();
  $mostCommented = getMostCommented();


  echo $twig->render('modificarUsuario.html', ['login'=>$login,'userData'=>$userData, 'formData'=>$formData,'totalRecipes'=>$totalRecipes,'mostCommented'=>$mostCommented]);
?>