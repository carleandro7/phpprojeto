<?php

namespace Controllers;

use Nucleo\Controller;

/**
 * Controlador da pagina inicial.
 *
 * Rotas atendidas:
 *   /                -> index()
 *   /home            -> index()
 *   /home/sobre      -> sobre()
 */
class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home/index', [
            'titulo' => 'Inicio',
        ]);
    }

    public function sobre(): void
    {
        $this->view('home/sobre', [
            'titulo' => 'Sobre o framework',
        ]);
    }
}
