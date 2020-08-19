<?php
  require_once "./vendor/autoload.php";
  include("db.php");



  $loader = new \Twig\Loader\FilesystemLoader('TW');
  $twig = new \Twig\Environment($loader);


  /*Validar los datos del formulario*/
  $form = []; /*Va a contener todos los datos*/

  
  $checked = false;
  $sent = 0;  /*Para indicarnos si se ha enviado a la base de datos o no la receta*/
  $endSession = 0;
  $commentDeleted = 0;
  $haveImages = 0;
  $uploadedImage = 0;
  
  
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


  /*Trabajamos con las variables recibidas*/


  if(!isset($_POST['modify']) & !isset($_POST['delete'])){  /*Queremos crear una nueva receta*/

    if(!isset($_POST['OK'])){
      /*Primero, vemos si han quedado imágenes en el directorio tmp/ de algún intento anterior de crear una receta, y las borramos*/
        $files = glob("tmp/*");

        foreach($files as &$singleFile){
          if(is_file($singleFile)){
            unlink($singleFile);
          }
        }
    }

  
    $result['reason'] = 0;    
    $result['form']['authorName'] = $_SESSION['user'];  /*Ya ponemos desde primera hora el nombre del usuario*/
    $result['form']['authorSurname'] = $userData['apellidos'];  /*Ya ponemos desde primera hora los apeliidos del usuario*/    
  }

  if($_SERVER["REQUEST_METHOD"] == "POST"){

    if(isset($_POST['modify']) | isset($_POST['delete'])){  /*Queremos modificar o eliminar la receta*/

      if(isset($_POST['modify'])){
        if($_POST['modify'] == 1){  /*Modificamos receta*/
          /*MOTIVOS PARA ESTAR EN EL FORMULARIO --> 0: CREAR RECETA / 1: MODIFICAR RECETA / 2: ELIMINAR RECETA*/
          $result['reason'] = 1;
        }
      }

      if(isset($_POST['delete'])){
        if($_POST['delete'] == 1){  /*Vamos a borrar la receta*/     
          $result['reason'] = 2;
          
        }
      }


        $recipeName = $_POST['recipeName']; /*El nombre de la receta*/
        $getData = getData($recipeName); /*Reutilizamos la función*/
        $result['recipeID'] = $getData['id'];

        /*Parámetros que podemos coger directamente*/

        $fotos = photosFormat($getData['fotos']); /*Los ponemos en formato de string*/

        $form = ['title'=>$getData['nombre'],'describe'=>$getData['descripcion'],'steps'=>$getData['preparacion'],
                'ingredients'=>$getData['ingredientes'],'fotos'=>$fotos]; 
        
        $form['authorName'] = $getData['nombreautor'];
        $form['authorSurname'] = $getData['apellidosautor'];

        /*Tanto las categorías como las etiquetas tenemos que tratarlas, ya que las recuperamos como un array de la BD, y tenemos que devolverlas como un solo
          string, separado por comas*/

          /*LAS CATEGORIAS*/
          $categoriesString = $getData['etiquetas'][0];

          for($i=1; $i < sizeof($getData['etiquetas']); $i++){     /*Metemos todas las etiquetas/categorias en un string*/
            $categoriesString = $categoriesString.",".$getData['etiquetas'][$i];
          }

          $form['categories'] = $categoriesString;  /*Lo almacenamos en el formulario*/
        

        /*Por último lo metemos todo el formulario en el dato a enviar al html*/
        $result['form'] = $form;
      
    }
        
    if(isset($_POST['OK'])){  /*Variable que nos indica si todos los campos están correctos (pasamos a comprobar) o no (seguimos con el formulario sticky)*/

      if($_POST['OK'] == 0){  /*Algún dato sigue mal*/
          $result['reason'] = $_POST['reason'];
          $result['recipeID'] = $_POST['recipeID'];

          if($result['reason'] == 0){ /*Vamos a crear, por lo que el autor es el propio usuario que está escribiendo la receta*/
            $authorName = $_SESSION['user'];
            $authorSurname = $userData['apellidos'];
          }
      
          else{ /*Tenemos que poner el usuario creador, que puede o no coincidir con quien esté modificando/borrando la receta*/
            $getData = getData($_POST['title']);
            $authorName = $getData['nombreautor'];
            $authorSurname = $getData['apellidosautor'];
          }


          $title = $describe = $steps = $categories = $ingredients = "";
          $titleError = $describeError = $stepsError = $categoriesError = $ingredientsError = $imageError = "";

          /*Primero, checkeamos si tenemos que eliminar una imagen*/

          $splittedPhotos = separatePhotos($_POST['photosRecipe']); /*Devolvemos a las imágenes al formato array*/

          for($i = 0, $size = count($splittedPhotos); $i < $size; ++$i){  /*Vamos a ver sobre que imagen hemos pulsado en borrar*/
            if(isset($_POST['deleteImage_'.$i])){ /*Si entramos es porque vamos a borrar la imagen "i" de la lista de imagenes de la receta*/
              $_POST['photosRecipe'] = removeImage($i,$splittedPhotos,$_POST['recipeID']);  /*Eliminamos de la lista la imagen*/
              $_POST['photosRecipe'] = photosFormat($_POST['photosRecipe']);  /*La volvemos a poner en el formato que necesitamos*/
              rename("Images/".$splittedPhotos[$i],"deletedImages/".$splittedPhotos[$i]); /*Movemos la imagen al directorio de deletedImages*/

              break;  
            }

          }
      
          if(empty($_POST["title"])){
            $titleError = "El título de la receta es obligatorio";
          }
          else{
            $title = test_input($_POST["title"]);
          }
      
          if(empty($_POST["describe"])){
            $describeError = "La descripción de la receta es obligatoria";
          }
          else{
            $describe = test_input($_POST["describe"]);
          }
            
          if(empty($_POST["steps"])){
            $stepsError = "Los pasos de la receta son obligatorios";
          }
          else{
            $steps = test_input($_POST["steps"]);
          }
      
          if(empty($_POST["categories"])){
            $categoriesError = "Las categorías de la receta son obligatorias";
          }
          else{
            $categories = test_input($_POST["categories"]);
          }
      
          if(empty($_POST["ingredients"])){
            $ingredientsError = "Los ingredientes de la receta son obligatorios";
          }
          else{
            $ingredients = test_input($_POST["ingredients"]);
          }

          /*Vemos si tenemos que eliminar alguna imagen del fichero temporal*/
          $tmpImages = getTmpImages();

          for($i = 0, $size = count($tmpImages); $i < $size; ++$i){
            if(isset($_POST['deleteTmpImage_'.$i])){
              unlink($tmpImages[$i]); /*Borramos la imagen correspondiente del fichero temporal*/
            }

          }

          /*Vamos a ver si el directorio /tmp está o no vacío, para mostrar las imágenes añadidas*/
          if(is_dir("tmp/")){
            $folder = scandir("tmp/");
            if(count($folder) > 2){  /*Tiene dentro algo más que la referencia al propio directorio y a su padre*/
              $haveImages = 1;
              $tmpImages = getTmpImages();
            }
          }
      
          
          $form = ['title'=>$title,'titleError'=>$titleError,'authorName'=>$authorName,'authorSurname'=>$authorSurname,'describe'=>$describe,'describeError'=>$describeError,
                  'steps'=>$steps,'stepsError'=>$stepsError,'categories'=>$categories,'categoriesError'=>$categoriesError,'ingredients'=>$ingredients,'ingredientsError'=>$ingredientsError,
                  'imageError'=>$imageError,'fotos'=>$_POST['photosRecipe'],'haveImages'=>$haveImages,'tmpImages'=>$tmpImages];

      
          $result['form'] = $form;
      
          /*Si no hemos tenido ningún error, por lo que vamos a pasar a mostrar al usuario el formulario con todo a "disable"(comprobar que todo fue OK)*/
          if($titleError == "" & $describeError == "" & $stepsError == "" & $categoriesError == "" & $ingredientsError == "" & $imageError == ""){ 
            /*Si nos ha llegado el valor de enviar; si no incluyesemos este if, al eliminar solo una foto ya pasaría a validar todo*/
            /*También tenemos en cuenta que si todo está OK pero acabamos de subir una imagen a tmp/ permitimos verlo en formato edición todo una vez antes de validar*/

            if(isset($_POST["Enviar"])){
              if(!isset($_POST['uploadImage'])){
                $checked = true;
                $result['checked'] = $checked;
              }

              else{
                if($_POST['uploadImage']!=1){
                  $checked = true;
                  $result['checked'] = $checked;
                }
              }

            }

          }
      }
      
      else{ /*Todos los datos están correctos y hemos checkeado, pasamos a operar con ellos*/
        $result['reason'] = $_POST['reason'];

        if($result['reason'] == 0){ /*Creamos receta*/
          /*Separamos en un array las etiquetas, y eliminamos los posibles espacios*/
          $auxTags = explode(",",$_POST['categoriesOK']);

          for($i = 0, $size = count($auxTags); $i < $size; ++$i){
            $auxTags[$i] = trim($auxTags[$i]);
          }


          addRecipe($_POST['titleOK'], $_POST['authorOK'],$_POST['authorSurnameOK'],$_POST['describeOK'], $_POST['stepsOK'], $auxTags, $_POST['ingredientsOK']);

          $sent = 1;
        }

        else if($result['reason'] == 1){  /*Modificamos la receta*/ 
           /*Separamos en un array las etiquetas, y eliminamos los posibles espacios*/
           $auxTags = explode(",",$_POST['categoriesOK']);

           for($i = 0, $size = count($auxTags); $i < $size; ++$i){
             $auxTags[$i] = trim($auxTags[$i]);
           }

          modifyRecipe($_POST['authorOK'],$_POST['authorSurnameOK'],$_POST['recipeID'], $_POST['titleOK'], $_POST['describeOK'], $_POST['ingredientsOK'],  $_POST['stepsOK'], $auxTags);
            $sent = 2;
        }


        $arrayImages = tmpImagesToFinalFolder();  /*Pasamos las imágenes de tmp al directorio final, y las añadimos a este array para meterlas en la BD*/
        addImages($arrayImages,$_POST['titleOK']);  /*Las añadimos a la BD*/



        if($result['reason'] == 2){/*Borramos la receta*/
          removeRecipe($_POST['authorOK'],$_POST['authorSurnameOK'],$_POST['titleOK']);
          $sent = 3;
        }

      }
    }
    
    if(isset($_POST['deleteComment'])){ /*Vamos a borrar un comentario*/
      $result['commentAuthorName'] = $_POST['commentAuthorNameToDelete'];
      $result['commentAuthorSurname'] = $_POST['commentAuthorSurnameToDelete'];
      $result['commentText'] = $_POST['commentTextToDelete'];
      $result['commentDate'] = $_POST['commentDateToDelete'];
      $result['commentRecipe'] = $_POST['commentRecipeToDelete'];
      $result['commentRecipeID'] = $_POST['commentRecipeIDToDelete'];
      $result['deleteComment'] = 1;
      $result['checked'] = true;
    }

    if(isset($_POST['deleteCommentOK'])){ /*Hemos pulsado el boton de borrar*/
      deleteComment($userData['nombre'],$userData['apellidos'],$_POST['commentAuthorName'],$_POST['commentAuthorSurname'],$_POST['commentText'],
                    $_POST['commentRecipe'],$_POST['commentRecipeID'],$_POST['commentDate']);
      $commentDeleted = 1;

    }
  }

   /*Función para validar la entrada de los formularios, evitando posibles ataques*/
   function test_input($data) {
      $data = trim($data);  /*Eliminamos espacios extras que sobren*/
      $data = stripslashes($data);  /*Eliminamos los "\" */
      $data = htmlspecialchars($data); /*Evitamos que se introduzcan caracteres especiales, traduciendolos a texto plano*/
      return $data;
    }

    /*Función para obtener las imagenes del directorio tmp*/
    function tmpImagesToFinalFolder(){

      $images = [];
      $files = glob("tmp/*");

      foreach($files as &$file){  /*Vemos los elementos del directorio*/
        if($file != "." && $file != ".."){

          /*Los nombres están en formato "tmp/...", por lo que los separamos */
          $fileAux = explode("/",$file);
          
          $name = $fileAux[1];

          rename($file,"Images/".$name); /*Lo movemos todo al fichero final*/
          chmod("Images/".$name,0777); /*Le añadimos permisos a las imágenes*/
          array_push($images,$name); /*Lo añadimos a la lista de imagenes a subir*/
        }
        
      }

      return $images;
    }

    /*Función para obtener las imagenes del fichero temporal, por lo que las mostraremos al usuario antes de subirlas*/
    function getTmpImages(){

      $images = [];
      $files = glob("tmp/*");

      foreach($files as &$file){  /*Vemos los elementos del directorio*/
        if($file != "." && $file != ".."){
          array_push($images,$file); /*Lo añadimos a la lista de imagenes a subir*/
        }
        
      }

      return $images;
    }

    /*Las imágenes vienen en un array, y para poder trabajar sin problema con ellas entre PHP y HTML, las pasamos a un string, donde las separamos mediante comas*/
    function photosFormat($photos){
      $auxPhotos = $photos[0];

      for($i = 1, $size = count($photos); $i < $size; ++$i){
        $auxPhotos = $auxPhotos.",".$photos[$i];
      }

      return $auxPhotos;
    }

    /*Para obtener la imagen a borrar, hacemos la operacion inversa; separar en un array*/
    function separatePhotos($photos){
      $auxPhotos = explode(",",$photos);

      for($i = 0, $size = count($auxPhotos); $i < $size; ++$i){
        $auxPhotos[$i] = trim($auxPhotos[$i]);
      }

      return $auxPhotos;
    }


  /*Listamos el total de las recetas*/
  $totalRecipes = getTotalRecipes();
  $mostCommented = getMostCommented();


  if($login == 0){  /*Si nos deslogueamos o no tenemos acceso nos envía a la página de inicio*/
    header('Location: portada.php?error=1');
  }

  if($sent == 1){
    header('Location: lista.php?sent=1');

  }

  
  if($sent == 2){
    header('Location: lista.php?sent=2');

  }
  
  
  if($sent == 3){
    header('Location: lista.php?sent=3');

  }

  if($commentDeleted == 1){
    header('Location: receta.php?name='.$_POST['commentRecipe'].'&deletedComment=1');

  }
  
  echo $twig->render('crearModificarReceta.html', ['result'=>$result,'totalRecipes'=>$totalRecipes,'login'=>$login,'userData'=>$userData,'mostCommented'=>$mostCommented]);

?>