<?php
  require_once "./vendor/autoload.php";
  include("db.php");

  $loader = new \Twig\Loader\FilesystemLoader('TW');
  $twig = new \Twig\Environment($loader);

  $result = []; /*Todo el contenido que vamos a enviar a la ṕagina sobre las recetas, relacionado con los resultados de las búsquedas*/
  $nameSearch; /*Variable donde almacenar lo introducido en el text input una vez tratada la cadena*/
  $ordered = 1; /*Variable para indicarnos si va en orden ascendente o descendente*/
  $sent = 0; /*Variable para saber por que hemos vuelto aqui tras la modificacion de una receta*/
  $userCreated = 0;
  $userUpdated = 0;
  $correctLogin = 0;
  $endSession = 0;
  $wrongAccess = 0;
  $userData = null;
  $userList = null;
  $userDeleted = 0;



  if(isset($_GET['userDeleted'])){
    $userDeleted = 1;
  }

  if(isset($_GET['userUpdated'])){
    $userUpdated = 1;
  }

  if(isset($_GET['userCreated'])){
    $userCreated = 1;
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
    $result['names'] = getNames("mostrar todos"); /*Si acabamos de hacer login vamos a mostrar la lista de las recetas*/
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

  if(isset($_GET['sent'])){
    $sent = $_GET['sent'];
  }


  if($_SERVER["REQUEST_METHOD"] == "GET"){


    if(isset($_GET['name'])){ /*Si obtenemos esta variable estamos en una lista de recetas*/

      /*primero, vemos si le hemos introducido texto que debe contener el nombre*/
      if(!empty($_GET["name"])){
        $nameSearch = test_input($_GET["name"]);
        $result['names'] = getNames($nameSearch);
      }
  
      else{
        $result['names'] = getNames("mostrar todos");
      }

      /*Después, de las recetas que aún pueda ser elegidas, vemos si he ha aplicado algún filtro de texto en el contenido*/
      if(!empty($_GET['contentText'])){  /*Hemos aplicado filtro*/
        $result['names'] = lookForContentCoincidence($result['names'],$_GET['contentText']);
      }

      /*Para las recetas que aún queden, buscamos si cumplen con el campo de ingrdientes*/
      if(!empty($_GET['categoriesText'])){
        $result['names'] = lookForContentCoincidence($result['names'],$_GET['categoriesText'],null);
      }


      /*A continuación, vemos si hemos aplicado algún filtro para escoger entre nuestras recetas o todas las de la página*/
      if(isset($_GET['myRecipes'])){  /*Si solo queremos mostrar las recetas propias*/
        $myRecipes = getMyRecipes($userData['id']); /*obtengo las recetas de dicho usuario*/
   
        $result['names'] = array_intersect($result['names'],$myRecipes); /*El resultado es la intersección de ambos arrays*/
      }
  
      /*Vemos si de entre las recetas que aún quedan el orden es alfabéticamente descendente o de más a menos comentado*/
      if(!empty($_GET["orderAndCommented"])){
        if($_GET["orderAndCommented"] == "noAlphabetical"){ /*Ordenamos en orden inverso*/
          $result['names'] = array_reverse($result['names']);
        }

        else{ /*Ordenamos de más a menos comentado*/
          $resultAux = getMostCommented(1);

          
          /*Una vez ya tenemos la lista de las recetas más comentadas en orden, compararemos con la lista de result, y eliminaremos $resultAux 
          las entradas que no estén en el primer array*/

          for($i = 0, $size = count($resultAux); $i < $size; ++$i){
            if(in_array($resultAux[$i],$result['names']) == false){ /*El elemento no está en el array*/
              unset($resultAux[$i]);  /*eliminamos el valor, dejandolo a null. Redimensionamos más adelante*/
            }

            if(in_array($resultAux[$i],$result['names'])){ /*Si está en $result['names'], lo eliminamos de ahí, de forma que al acabar el bucle solo tengamos las recetas que cumplan los parámetros y tengan 0 comentarios*/
              $val = array_search($resultAux[$i],$result['names']);
              unset($result['names'][$val]);
            }
          }

          /*El objetivo para redimensionar ahora es que si lo hacemos durante el bucle podríamos estar introduciendo resultados de más */
          $resultAux = array_values($resultAux);
          $result['names'] = array_values($result['names']);


          /*El método getMostCommented nos devuelve las recetas ordenadas de más a menos comentada, pero no incluye en la lista a las recetas con 0 comentarios. Para solucionar esto, 
          hemos eliminado en el bucle anterior de $result['names'] las recetas con al menos 1 comentario, dejando solo las recetas de 0 comentarios que cumplen todos los parámetnos en 
          este array. Por último, solo tendremos que añadirlo al final de $resultAux para obtener el resultado que esperabamos*/

          for($i = 0, $size = count($result['names']); $i < $size; ++$i){
            array_push($resultAux,$result['names'][$i]);
          }

          $result['names'] = $resultAux;

        }
      }

    }

    else if (isset($_GET['users'])){  /*Vamos a listar a los usuarios registrados*/
      $userList = listUsers();/*Obtenemos una lista con toda la info de los usuarios*/
    }

    else if(isset($_GET['validateUsers'])){ /*Listamos usuarios pendientes de validar*/
      $userList = listUsers(0);
    }

    else{ /*No recibimos variables GET (por ejemplo, nos logueamos o deslogueamos en la lista), mostramos toda la lista*/
      $result['names'] = getNames("mostrar todos");
    }
   
  }

  if($result != null){ /*Si no es nulo es porque estamos trabajando con recetas*/
     /*Vamos a obtener el ID del creador de las recetas*/
    $result['creatorID'] = getCreatorsID($result['names']);
  }
 


  /*Función para validar la entrada de los formularios, evitando posibles ataques*/
  function test_input($data) {
    $data = trim($data);  /*Eliminamos espacios extras que sobren*/
    $data = stripslashes($data);  /*Eliminamos los "\" */
    $data = htmlspecialchars($data); /*Evitamos que se introduzcan caracteres especiales, traduciendolos a texto plano*/
    return $data;
  }

  if(isset($_POST['unlogin']) | (isset($_GET['users']) & $userData['tipo'] != "admin")){  
    header("Location:portada.php");
  }


  /*A veces puede ocurrir que se nos queda algún hueco vacío en el array, por lo que volvemos a ponerlo todo en su sitio antes de enviarlo; de lo contrario, esos huecos vacíos 
  ocuparían espacio de alguna receta en la lista mostrada como resultado*/
  
  if($result != null){
    $result['names'] = array_values($result['names']);
  }


  /*Añadir el total de las recetas*/
  $totalRecipes = getTotalRecipes();
  $mostCommented = getMostCommented();


  echo $twig->render('lista.html', ['result'=>$result,'userList'=>$userList,'totalRecipes'=>$totalRecipes,'login'=>$login,'filename'=>$filename, 'loginError'=>$loginError,
                                    'sent'=>$sent,'userData'=>$userData,'userDeleted'=>$userDeleted, 'userUpdated'=>$userUpdated,'userCreated'=>$userCreated,
                                    'mostCommented'=>$mostCommented]);

?>