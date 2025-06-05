<?php

return array (
  'semantic_search_threshold' => 0.75,
  'keyword_search_boost' => 1.2,
  'category_priority' => 
  array (
    'Atención a Usuarios' => 1.5,
    'Soporte Técnico' => 1.4,
    'Red y Conectividad' => 1.3,
    'Digitalización' => 1.2,
    'Información General' => 1.0,
  ),
  'department_focus' => 
  array (
    'Dirección de Infraestructura y Servicios Tecnológicos' => 1.3,
  ),
  'content_quality_filters' => 
  array (
    'min_content_length' => 50,
    'require_active' => true,
    'boost_with_contact_info' => 1.1,
  ),
);
