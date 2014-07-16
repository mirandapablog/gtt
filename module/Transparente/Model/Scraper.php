<?php
namespace Transparente\Model;

class Scraper
{
    public static function fetchData($xpaths, \Zend\Dom\Query $dom)
    {
        $element = [];
        foreach($xpaths as $key => $path) {
            if (is_array($path)) {
                $element[$key] = self::fetchData($path, $dom);
            } else {
                if (!$path) {
                    $element[$key] = null;
                } else {
                    if ($path[0] == '/') {
                        $nodes = $dom->queryXpath($path);
                    } else {
                        $nodes = $dom->execute($path);
                    }
                    $nodesCount = count($nodes);
                    if ($nodesCount > 1) {
                        throw new \Exception("path '$path' not expecific enought, returned $nodesCount");
                    }
                    $node = $nodes->current();
                    if (!is_a($node , 'DOMElement')) {
                        $element[$key] = null;
                    } else {
                        $element[$key] = $node->nodeValue;
                    }
                }
            }
        }
        return $element;
    }

    /**
     * Returns a cached crawled URL
     *
     * @param string $url
     * @param string $method
     *
     * @return \Zend\Dom\Query
     */
    public static function getCachedUrl($url, $method='GET')
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $key     = md5($method.$url.'0');
        $cache = \Zend\Cache\StorageFactory::factory([
            'adapter' => [
                'name'    => 'filesystem',
                'ttl'     => PHP_INT_MAX,
                'options' => ['cache_dir' => realpath('./data/cache')],
            ],
            'plugins' => array('serializer'),
        ]);
        if ($cache->hasItem($key)) {
            $dom = $cache->getItem($key);
        } else {
            $content       = file_get_contents($url);
            $content       = iconv('utf-8', 'iso-8859-1', $content);
            $dom           = new \Zend\Dom\Query($content);
            $cache->setItem($key, $dom);
        }
        return $dom;
    }

    private function humanizarNombreDeEmpresa($nombre)
    {
        $nombre  = str_replace('SOCIEDAD ANONIMA', 'S.A.', $nombre);
        $nombres = preg_split('/[\s,]+/', mb_strtolower($nombre, 'UTF-8'));
        foreach ($nombres as $key => $nombre) {
            if (preg_match('/\./', $nombre)) {
                $nombres[$key] = strtoupper($nombre);
            } else {
                $nombres[$key] = ucfirst($nombre);
            }
        }
        $nombre = implode(' ', $nombres);
        return $nombre;
    }

    /**
     * Obtiene los nombres comerciales de los proveedores
     *
     * @param int $id
     * @return array
     */
    public function scrapNombresComercialesDelProveedor($id)
    {
        $página  = $this->getCachedUrl('http://guatecompras.gt/proveedores/consultaProveeNomCom.aspx?rqp=8&lprv='.$id);
        $xpath   = '//*[@id="MasterGC_ContentBlockHolder_dgResultado"]//tr[not(@class="TablaTitulo")]/td[2]';
        $nodos   = $página->queryXpath($xpath);
        $nombres = [];
        foreach($nodos as $nodo) {
            $nombre = $this->humanizarNombreDeEmpresa($nodo->nodeValue);
            if (in_array($nombre, $nombres)) continue;
            $nombres[] = $nombre;
        }
        sort($nombres);
        return $nombres;
    }

    /**
     * Lee todos los datos del proveedor según su ID
     *
     * @param int $id
     * @return array
     */
    public function scrapProveedor($id)
    {
        $url               = "http://guatecompras.gt/proveedores/consultaDetProvee.aspx?rqp=8&lprv={$id}";
        $páginaDelProveedor = $this->getCachedUrl($url);

        /**
         * Que valores vamos a buscar via xpath en la página del proveedor
         *
         * Usamos de nombre los campos de la base de datos para después solo volcar el arreglo con los resultados directo a
         * la DB.
         *
         * @var array
         */
        $xpaths = [
            'nombre'               => '//*[@id="MasterGC_ContentBlockHolder_lblNombreProv"]',
            'nit'                  => '//*[@id="MasterGC_ContentBlockHolder_lblNIT"]',
            'status'               => '//*[@id="MasterGC_ContentBlockHolder_lblHabilitado"]',
            'tiene_acceso_sistema' => '//*[@id="MasterGC_ContentBlockHolder_lblContraSnl"]',
            'domicilio_fiscal'     => [
                'updated'      => 'div#MasterGC_ContentBlockHolder_divDomicilioFiscal span.AvisoGrande span.AvisoGrande',
                'departamento' => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioFiscal2"]//tr[1]//td[2]',
                'municipio'    => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioFiscal2"]//tr[2]//td[2]',
                'direccion'    => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioFiscal2"]//tr[3]//td[2]',
                'telefonos'    => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioFiscal2"]//tr[4]//td[2]',
                'fax'          => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioFiscal2"]//tr[5]//td[2]',
            ],
            'domicilio_comercial'     => [
                'updated'      => null,
                'departamento' => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioComercial2"]//tr[3]//td[2]',
                'municipio'    => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioComercial2"]//tr[4]//td[2]',
                'direccion'    => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioComercial2"]//tr[5]//td[2]',
                'telefonos'    => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioComercial2"]//tr[6]//td[2]',
                'fax'          => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioComercial2"]//tr[7]//td[2]',
            ],
            'url'                 => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioComercial2"]//tr[1]//td[2]',
            'email'               => '//*[@id="MasterGC_ContentBlockHolder_pnl_domicilioComercial2"]//tr[2]//td[2]',
            'rep_legales_updated' => '//*[@id="MasterGC_ContentBlockHolder_divRepresentantesLegales"]//span/span',
        ];

        $proveedor = ['id' => $id] + $this->fetchData($xpaths, $páginaDelProveedor);

        // después de capturar los datos, hacemos un postproceso

        $proveedor['nombre']               = $this->humanizarNombreDeEmpresa($proveedor['nombre']);
        $proveedor['status']               = ($proveedor['status'] == 'HABILITADO');
        $proveedor['tiene_acceso_sistema'] = ($proveedor['tiene_acceso_sistema'] == 'CON CONTRASEÑA');
        // descartar direcciones vacías
        if ($proveedor['domicilio_fiscal']['direccion'] == '[--No Especificado--]' ||
            $proveedor['domicilio_fiscal']['municipio'] == '[--No Especificado--]') {
            unset($proveedor['domicilio_fiscal']);
        }
        if ($proveedor['domicilio_comercial']['direccion'] == '[--No Especificado--]' ||
            $proveedor['domicilio_comercial']['municipio'] == '[--No Especificado--]') {
            unset($proveedor['domicilio_comercial']);
        }

        // algunas fechas no están bien parseadas
        $proveedor['rep_legales_updated']  = strptime($proveedor['rep_legales_updated'], '(Datos recibidos de la SAT el: %d.%b.%Y %T ');
        $proveedor['rep_legales_updated']  = 1900+$proveedor['rep_legales_updated']['tm_year']
                                            . '-' . (1 + $proveedor['rep_legales_updated']['tm_mon'])
                                            . '-' . ($proveedor['rep_legales_updated']['tm_mday'])
                                            ;
        $proveedor['url']                  = ($proveedor['url']   != '[--No Especificado--]') ? $proveedor['url'] : null;
        $proveedor['email']                = ($proveedor['email'] != '[--No Especificado--]') ? $proveedor['email'] : null;
        return $proveedor;
    }

    /**
     * Conseguir todos los proveedores adjudicados del año en curso
     *
     * @return multitype:Ambigous <multitype:, number>
     *
     * @todo Solo los proveedores que no están en la DB son los que vamos a barrer
     */
    public function scrapProveedores()
    {
        $year            = date('Y');
        $proveedoresList = self::getCachedUrl('http://guatecompras.gt/proveedores/consultaProveeAdjLst.aspx?lper='.$year);
        $xpath           = "//a[starts-with(@href, './consultaDetProveeAdj.aspx')]";
        $proveedoresList = $proveedoresList->queryXpath($xpath);
        $proveedores     = [];
        foreach($proveedoresList as $nodo) {
            /* @var $proveedor DOMElement */
            // El link apunta a las adjudicaciones/projectos del proveedor, pero de aquí sacamos el ID del proveedor
            $url = parse_url($nodo->getAttribute('href'));
            parse_str($url['query'], $url);
            $idProveedor  = $url['lprv'];
            $data          = $this->scrapProveedor($idProveedor);
            $data         += ['nombres_comerciales'    => $this->scrapNombresComercialesDelProveedor($idProveedor)];
            $data         += ['representantes_legales' => $this->scrapRepresentantesLegales($idProveedor)];
            $proveedores[] = $data;
        }
        return $proveedores;
    }

    public function scrapRepresentantesLegales($id)
    {
        $página    = self::getCachedUrl('http://guatecompras.gt/proveedores/consultaprrepleg.aspx?rqp=8&lprv=' . $id);
        $xpath     = '//*[@id="MasterGC_ContentBlockHolder_dgResultado"]//tr[not(@class="TablaTitulo")]/td[2]/a';
        $nodos     = $página->queryXpath($xpath);
        $elementos = [];
        foreach($nodos as $nodo) {
            $url         = parse_url($nodo->getAttribute('href'));
            parse_str($url['query'], $url);
            $id          = $url['lprv'];
            if (in_array($id, $elementos)) continue;
            $elementos[] = (int) $id;
        }
        sort($elementos);
        return $elementos;
    }

}