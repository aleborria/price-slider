# Filtro de Precios en Slider - Magento 2

En este repositorio vamos a poder encontrar la lógica que se aplico en Summa Solutions para poder contar con un slider de precios en los filtros, dándo soporte a ElasticSearch Nativo y LiveSearch 1.2

Descripción de Archivos:

- `Block/Navigation/FilterPriceRenderer.php`: Este es el bloque encargado de renderizar las configuraciones para el Slider 
- `Block/Navigation.php`: Este archivo es un override al bloque default de Magento, para aplicar último el filtro de precios, así al tener la collection filtrada sin precios, podemos obtener los máximos y mínimos para setear sin haber filtrado y podes volver a filtrar por precio teniendo un filtro de precios activo.
- `Model/Layer/Filter/Price.php`: Este modelo extiende del modelo base de precio de Magento, donde cambiamos los facetados (todavia no está funcional), aplicamos máximos y mínimos, valores actuales (currentValue)
- `etc/adminhtml/system.xml`: agrega config en panel de admin para habilitar o no el slider de precios. 
- `etc/frontend/di.xml`: Reemplaza los modelos de los filtros de precios por `Model/Layer/Filter/Price.php` y reemplaza los Navigation Nativos por `Block/Navigation.php`
-  `view/frontend/layout`: En estos layouts reemplazamos el template e instanciamos un bloque hijo de los filtros para `Block/Navigation/FilterPriceRenderer.php`
-  `view/frontend/templates/view.phtml`: Se reemplaza el renderer cuando el filtro es PRICE
-  `view/frontend/templates/filter_price.phtml`: Template del slider de precios
-  `view/frontend/web/js/range-slider-widget.js`: Jquery Widget que genera y manipula el slider de precios

 

Alejandro Borria

aleborria@gmail.com / aborria@summasolutions.net