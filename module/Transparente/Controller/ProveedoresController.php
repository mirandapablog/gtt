<?php
namespace Transparente\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use DoctrineModule\Paginator\Adapter\Collection as Adapter;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Paginator\Paginator;
use Zend\View\Model\ViewModel;

class ProveedoresController extends AbstractActionController
{

    /**
     * Listar Proveedores
     *
     * @todo paginar
     * @todo ordenar por columna seleccionada
     */
    public function indexAction()
    {
        $proveedoresModel = $this->getServiceLocator()->get('Transparente\Model\ProveedorModel');
        $entities         = $proveedoresModel->findAll();
        $paginator        = new Paginator(new Adapter(new ArrayCollection($entities)));
        if (!empty($_GET['page'])) {
            $paginator->setCurrentPageNumber($_GET['page']);
        }
        return new ViewModel(compact('paginator'));
    }

    /**
     * Ver los detalles de un proveedor
     */
    public function detallesAction()
    {
        $id               = $this->params('id');
        $proveedoresModel = $this->getServiceLocator()->get('Transparente\Model\ProveedorModel');
        $entity           = $proveedoresModel->find($id);
        return new ViewModel(compact('entity'));
    }

    /**
     * Listar proveedores sin dirección fiscal
     *
     * @todo paginar
     * @todo ordenar por nombre pre-seleccionado
     * @todo ordenar por columna seleccionada
     */
    public function noFiscalAction()
    {
        $proveedoresModel = $this->getServiceLocator()->get('Transparente\Model\ProveedorModel');
        $entities         = $proveedoresModel->findByNoDomicilioFiscal();
        $paginator        = new Paginator(new Adapter(new ArrayCollection($entities)));
        if (!empty($_GET['page'])) {
            $paginator->setCurrentPageNumber($_GET['page']);
        }
        return new ViewModel(compact('paginator'));
    }

}