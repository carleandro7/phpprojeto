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
        $alunos = $this->modelo('Aluno');

        $this->view('home/index', [
            'titulo'        => 'Inicio',
            'totalAlunos'   => $alunos->contar(),
            'media'         => $alunos->mediaGeral(),
            'totalPorCurso' => $alunos->totalPorCurso(),
        ]);
    }

    public function sobre(): void
    {
        $this->view('home/sobre', [
            'titulo' => 'Sobre o framework',
        ]);
    }
}
