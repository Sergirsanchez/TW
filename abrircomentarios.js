/*-------------------------FUNCIÓN PARA ABRIR LA CAJA DE COMENTARIOS -------------------*/
function openCommentMenu(){
  
    var elements = document.getElementById("commentMenu");
    elements.style.display="block";
}

/*-------------------------FUNCIÓN PARA CERRAR LA CAJA DE COMENTARIOS -------------------*/
function closeCommentMenu(){
  
    var elements = document.getElementById("commentMenu");
    elements.style.display="none";
}



/*IMPORTANTE: LA VALIDACIÓN DE LAS IMÁGENES, AL TRABAJAR CON FICHEROS, LA HE DEJADO SOLO PARA EL LADO DEL SERVIDOR, POR LO QUE NO SE ENCUENTRA AQUÍ NADA RELATIVO A LAS IMÁGENES*/

/*---------------------------------------------------------- VALIDACIÓN DEL FORMULARIO DE RECETAS -------------------------------------*/
function RecipeNameIsOK(recipeName){
    result = true

    if(recipeName == ""){
        result = false;
    }

    return result;
}

function RecipeDescriptionIsOK(recipeDescription){
    result = true

    if(recipeDescription == ""){
        result = false;
    }

    return result;
}

function RecipeStepsIsOK(recipeSteps){
    result = true

    if(recipeSteps == ""){
        result = false;
    }

    return result;
}

function RecipeCategoriesIsOK(recipeCategories){
    result = true

    if(recipeCategories == ""){
        result = false;
    }

    return result;
}

function RecipeIngredientsIsOK(recipeIngredients){
    result = true

    if(recipeIngredients == ""){
        result = false;
    }

    return result;
}

/*Función del formulario*/
function validarFormularioReceta(e) {
    err = 0;

    /*Si no tenemos nada en el campo nombre, devolvemos el error*/
    if(RecipeNameIsOK(e.target.title.value) == false){
        document.getElementById('titleError').innerHTML = "Por favor, introduce un nombre de receta"
        err = 1
    }

    else{
        document.getElementById('titleError').innerHTML = "" 
    }

    /*Si no tenemos nada en el campo descripcion, devolvemos el error*/
    if(RecipeDescriptionIsOK(e.target.describe.value) == false){
        document.getElementById('describeError').innerHTML = "Por favor, introduce un breve resumen"
        err = 1
    }
    else{
        document.getElementById('describeError').innerHTML = "" 
    }

    /*Si no tenemos nada en el campo pasos, devolvemos el error*/
    if(RecipeStepsIsOK(e.target.steps.value) == false){
        document.getElementById('stepsError').innerHTML = "Por favor, introduce los pasos de la receta"
        err = 1
    }
    else{
        document.getElementById('stepsError').innerHTML = "" 
    }

    /*Si no tenemos nada en el campo categorias, devolvemos el error*/
    if(RecipeCategoriesIsOK(e.target.categories.value) == false){
        document.getElementById('categoriesError').innerHTML = "Por favor, introduce las categorías"
        err = 1
    }
    else{
        document.getElementById('categoriesError').innerHTML = "" 
    }

    /*Si no tenemos nada en el campo ingredientes, devolvemos el error*/
    if(RecipeIngredientsIsOK(e.target.ingredients.value) == false){
        document.getElementById('ingredientsError').innerHTML = "Por favor, introduce los ingredientes"
        err = 1
    }
    else{
        document.getElementById('ingredientsError').innerHTML = "" 
    }


    /*Si tenemos algún error, detenemos el envío de formulario al servidor*/
    if(err!=0) {
        e.preventDefault();
        return false;
    } 

    else{
        return true;
    }

}


/*--------------------------- VALIDACIÓN DEL FORMULARIO DE LOS DATOS DE LOS USUARIOS ---------------------------------*/


/*Función para ver si hemos dejado el campo del nombre o del apellido vacío, las cuestiones de seguridad en la cadena las tendremos en cuenta en el lado del servidor*/
function nameIsOKUser(name){
    result = true

    if(name == ""){
        result = false;
    }

    return result;
}

/*Función para ver si el correo está o no vacío, y si el formato es el correcto*/
function emailIsOKUser(email){
    result = 0 /*Result = 0 nos va a indicar que el email está introducido de forma correcta*/

    if(email == ""){
        result = 1 /*Result = 1 nos indica que no se introdujo nada en el campo de email*/
    }

    return result;

}

/*Función para ver si ambos campos de contraseña coinciden*/
function passwordIsOKUser(password1, password2){
    result = false;

    if(password1 == password2){
        result = true;
    }

    return result;
}

/*Función del formulario*/
function validarFormularioUsuario(e) {
    err = 0;
    
    /*Si no tenemos nada en el campo nombre, devolvemos el error*/
    if(nameIsOKUser(e.target.userName.value) == false){
        document.getElementById('userNameError').innerHTML = "Por favor, intorduce el nombre"
        err = 1
    }

    else{
        document.getElementById('userNameError').innerHTML = ""

    }

    /*Si no tenemos nada en el campo apellidos, devolvemos el error*/
    if(nameIsOKUser(e.target.userSurname.value) == false){
        document.getElementById('userSurnameError').innerHTML = "Por favor, introduce los apellidos"
        err = 1
    }

    else{
        document.getElementById('userSurnameError').innerHTML = ""

    }

    /*Si no tenemos nada en el campo email, devolvemos el error*/
    if(emailIsOKUser(e.target.userEmail.value) == 1){
        document.getElementById('userEmailError').innerHTML = "Por favor, introduce el email"
        err = 1
    }

    /*Email introducido con el formato adecuado*/
    if(emailIsOKUser(e.target.userEmail.value) == 0){
        document.getElementById('userEmailError').innerHTML = ""

    }

    /*Vemos si las contraseñas coinciden*/
    if(passwordIsOKUser(e.target.newPassword1.value,e.target.newPassword2.value) == false){
        document.getElementById('userPasswordError').innerHTML = "Las contraseñas no coinciden"
        err = 1
    }
    
    else{
        document.getElementById('userPasswordError').innerHTML = ""

    }

    /*Si tenemos algún error, detenemos el envío de formulario al servidor*/
    if(err!=0) {
        e.preventDefault();
        return false;
    } 

    else{
        return true;
    }

}


/*--------------------------- VALIDACIÓN DEL FORMULARIO DE CONTACTO ---------------------------------*/




/*Función para ver si el correo está o no vacío, y si el formato es el correcto*/
function emailIsOKContact(email){
    result = 0 /*Result = 0 nos va a indicar que el email está introducido de correcta*/

    if(email == ""){
        result = 1 /*Result = 1 nos indica que no se introdujo nada en el campo de email*/
    }

    return result;
}

/*Función para ver si el formato del teléfono es adecuado, en el caso de escribirilo*/
function phoneIsOKContact(phone){
    result = false;

    
    if(phone == ""){
        result = true;
    }

    else{
        if(phone.match(/^(\(\+[0-9]{2}\))?\s*[0-9]{3}\s*[0-9]{6}$/) != null){
            result = true;
        }
    }


    return result;
}

/*Función para ver si hemos dejado el campo de texto del comentario vacío, las cuestiones de seguridad en la cadena las tendremos en cuenta en el lado del servidor*/
function messageIsOKContact(message){
    result = true

    if(message == ""){
        result = false;
    }

    return result;
}


/*Función del formulario*/
function validarFormularioContacto(e) {
    err = 0;
    
    /*Si no tenemos nada en el campo email, devolvemos el error*/
    if(emailIsOKContact(e.target.emailContact.value) == 1){
        document.getElementById('emailError').innerHTML = "Por favor, introduce el email"
        err = 1
    }

    /*Email correcto*/
    if(emailIsOKContact(e.target.email.value) == 0){
        document.getElementById('emailError').innerHTML = ""
    }

    /*Vemos si las contraseñas coinciden*/
    if(phoneIsOKContact(e.target.phone.value) == false){
        document.getElementById('phoneError').innerHTML = "El teléfono no tiene un formato válido"
        err = 1
    }

    else{
        document.getElementById('phoneError').innerHTML = ""

    }

    /*Si no tenemos nada en el campo apellidos, devolvemos el error*/
    if(messageIsOKContact(e.target.comment.value) == false){
        document.getElementById('commentError').innerHTML = "Por favor, introduce un comentario"
        err = 1
    }

    else{
        document.getElementById('commentError').innerHTML = ""

    }
        

    /*Si tenemos algún error, detenemos el envío de formulario al servidor*/
    if(err!=0) {
        e.preventDefault();
        return false;
    } 

    else{
        return true;
    }

}


onload = function() {
    if(document.getElementById('notCheckedContact') != null){
        document.getElementById('notCheckedContact').addEventListener('submit',validarFormularioContacto);

    }

    else if(document.getElementById('modifyUserForm') != null){
        document.getElementById('modifyUserForm').addEventListener('submit',validarFormularioUsuario);

    }

    else if(document.getElementById('createRecipe') != null){
        document.getElementById('createRecipe').addEventListener('submit',validarFormularioReceta);
        
        if(document.getElementById('uploadPhotos') != null){
            document.getElementById('uploadPhotos').addEventListener('change',loadImages);

        }

    }
}


/*---------------------------------- FUNCIÓN PARA CARGAR LAS FOTOS EN TMP/ Y DEVOLVERLAS USANDO AJAX ----------------------*/
function loadImages(e){
            
            var obj = new XMLHttpRequest();

            obj.open("POST", "subirImagenes.php")
            obj.onreadystatechange = function(){
                if(obj.readyState === 4 && obj.status === 200) {
                    addPhotos(obj.responseText);
                    
                }
            }
    
            formdata = new FormData (document.getElementById('createRecipe'))   /*Enviamos el formulario*/
            obj.send(formdata)
}


/*------------------------------- FUNCIÓN EN EL CASO DE ÉXITO ------------------------------*/
function addPhotos(result){
    resultAJAX = "";

    if(result == 1 | result == 2){
        if(result == 1){    /*ERROR 1: Extensión no permitida*/
            document.getElementById('uploadPhotosError').innerHTML = "Extensión no permitida"
        }

        if(result == 2){    /*ERROR 2: Fichero muy grande*/
            document.getElementById('uploadPhotosError').innerHTML = "Fichero demasiado grande"
        }
    }

    else{   /*En el caso de que no haya errores*/

        if(document.getElementById('uploadPhotosError') != null){
            document.getElementById('uploadPhotosError').innerHTML = ""

        }
        
        console.log("VALOR DE RESULTADO: "+result);
        
        /* Las imagenes son devueltas como un único string del tipo --> ["tmp/imagen1","tmp/imagen2"...], por lo que vamos a separarlas para poder mostarlas*/
        result = result.split(",");


        result[0] = result[0].slice(1,(result[0].length)-1) /*En la primera foto eliminamos el [ que le precede*/

        for(var i = 0; i < result.length; i++){
            result[i] = result[i].slice(1,4) + result[i].slice(5,(result[i].length));   /*Eliminamos las "" que hay al inicio y fin de cada nombre*/
            resultAJAX += '<div class="photoInputBlock"><img src="'+result[i]+'"><input type="submit" name="deleteTmpImage" value="Eliminar" disabled></div>'
        }


        resultAJAX+='<input type="hidden" name="uploadImage" value="1" >'

        document.getElementById('photosContainer').innerHTML = resultAJAX
    }
    

}
