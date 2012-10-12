<?php

namespace Enroporra\EnroporraBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Enroporra\EnroporraBundle\EntityExtended\cPorrista;
use Enroporra\EnroporraBundle\EntityExtended\cGoleador;

class ClasificacionController extends Controller
{

    public $base;
    public $clasificacion;
    public $PROXIMOS_PARTIDOS;
    public $amigos;

    public function __construct()
    {
        $this->PROXIMOS_PARTIDOS = 4;
    }

    public function indexAction()
    {
        // Estas dos líneas van aquí porque no las puedo meter en el constructor. Dice que no puedo aplicar el método get y sospecho que es porque no ha terminado todavía de construir el objeto
        $this->base = $this->get("enroporra.base");
        $this->base->init($this->getDoctrine());
        $this->getAmigos();

        $this->clasificacion("completa");

        return $this->render('EnroporraBundle:Front:clasificacion.html.twig', array('base' => $this->base, 'clasificacion' => $this->clasificacion)
        );
    }

    public function amigosAction()
    {
        // Estas dos líneas van aquí porque no las puedo meter en el constructor. Dice que no puedo aplicar el método get y sospecho que es porque no ha terminado todavía de construir el objeto
        $this->base = $this->get("enroporra.base");
        $this->base->init($this->getDoctrine());
        $this->getAmigos();

        $this->clasificacion("amigos");

        return $this->render('EnroporraBundle:Front:clasificacion.html.twig', array('base' => $this->base, 'clasificacion' => $this->clasificacion)
        );
    }

    public function getAmigos()
    {
        $this->amigos = array();
        $cookieAmigos = $this->base->getCookieAmigos();

        if (!strlen($cookieAmigos))
            return;

        $temp = explode(",", $cookieAmigos);
        if (!count($temp))
            return;
        foreach ($temp as $amigo)
            $this->amigos[] = strtolower(str_replace("'", "", $amigo));
    }

    public function cmp($a, $b)
    {
        if ($a->getPuntos() == $b->getPuntos()) {
            return (strtoupper(str_replace("Á", "A", str_replace("Ó", "O", $a->getNombre()))) < strtoupper(str_replace("Á", "A", str_replace("Ó", "O", $b->getNombre())))) ? -1 : 1;
        }
        return ($a->getPuntos() < $b->getPuntos()) ? 1 : -1;
    }

    public function clasificacion($tipo = "completa")
    {

        $this->clasificacion["tipo"] = $tipo;
        $this->clasificacion["destacados"] = ($tipo == "completa") ? 5 : 1;

        $repPartidos = $this->getDoctrine()->getRepository('EnroporraBundle:Partido');
        $repPorristas = $this->getDoctrine()->getRepository('EnroporraBundle:Porrista');
        $repGoleador = $this->getDoctrine()->getRepository('EnroporraBundle:Goleador');

        $partidos = $repPartidos->createQueryBuilder('p')
            ->where('p.fecha <= :fecha', 'p.resultado1 >= :resultado1')
            ->setParameter('fecha', date("Y-m-d"))
            ->setParameter('resultado1', 0)
            ->getQuery()
            ->getResult();

        $this->clasificacion["numeroPartidos"] = count($partidos);

        if ($this->clasificacion["numeroPartidos"]) {

            $nombresExistentes = array();
            $goleadoresActuales = array();

            $comenzoSegundaFase = (date("Y-m-d H:i:s") > "2012-06-21 20:45:00");
            $condicionFase = ($comenzoSegundaFase) ? 'p.fase >= :fase' : 'p.fase = :fase';

            $proximosPartidos = $repPartidos->createQueryBuilder('p')
                ->where('p.resultado1 < :resultado1', $condicionFase)
                ->setParameter('fase', 1)
                ->setParameter('resultado1', 0)
                ->orderBy('p.fecha', 'ASC', 'p.hora', 'ASC')
                ->setMaxResults($this->PROXIMOS_PARTIDOS)
                ->getQuery()
                ->getResult();

            $porristasBD = $repPorristas->createQueryBuilder('p')
                ->where('p.pagado = :pagado')
                ->setParameter('pagado', 1)
                ->getQuery()
                ->getResult();

            // Generamos un array de cPorrista que tiene métodos y variables extendidas a Porrista. Así no tocamos la Entity
            $porristas = array();
            foreach ($porristasBD as $porrista) {
                $porristas[] = new cPorrista($porrista, $this->getDoctrine(), $this->base);
            }

            foreach ($porristas as $clave => $porrista) {

                $porristas[$clave]->setNombre($this->get("enroporra.apellidos_con_tilde")->convertir($porrista->getNombre() . " " . $porrista->getApellido()));
                if (in_array($porrista->getNombre(), $nombresExistentes))
                    $porristas[$clave]->setNombre($porrista->getNombre() . " (2)");
                else
                    $nombresExistentes[] = $porrista->getNombre();

                if (!isset($goleadoresActuales[$porrista->getIdGoleador()->getId()])) {
                    $goleadoresActuales[$porrista->getIdGoleador()->getId()] = new cGoleador($porrista->getIdGoleador(), $this->getDoctrine());
                }
                $porristas[$clave]->setGoleador($goleadoresActuales[$porrista->getIdGoleador()->getId()]);
                $porristas[$clave]->calculaPuntos();

                if ($tipo == "amigos") {
                    if (!in_array(strtolower($porrista->getNick()), $this->amigos))
                        array_splice($porristas, $clave, 1);
                }
            }

            usort($porristas, array($this, "cmp"));

            $puestoClasificacion = $puestoImpreso = $ultimoPuestoImpreso = 1;
            $puntuacionAnterior = "";
            $par = true;
            foreach ($porristas as $clave => $porrista) {

                if ($par) {
                    $porristas[$clave]->setBgColor("#DDDDDD");
                    $par = false;
                } else {
                    $porristas[$clave]->setBgColor("#EEEEEE");
                    $par = true;
                }
                if (isset($this->base->cookieNick) && strtolower($porrista->getNick()) == strtolower($this->base->cookieNick)) {
                    $porristas[$clave]->setBgColor("#FFFF00");
                } else if ($tipo == "completa" && in_array(strtolower($porrista->getNick()), $this->amigos)) {
                    $porristas[$clave]->setBgColor("#DDAA33");
                }

                $puestoImpreso = ($puntuacionAnterior == $porrista->getPuntos()) ? "" : $puestoClasificacion;
                $porristas[$clave]->setClasificacion($puestoImpreso);

                if ($puestoImpreso == $puestoClasificacion)
                    $ultimoPuestoImpreso = $puestoImpreso;
                $porristas[$clave]->setDestacado($ultimoPuestoImpreso <= $this->clasificacion["destacados"]);

                $puntuacionAnterior = $porrista->getPuntos();
                $puestoClasificacion++;
            }

            $this->clasificacion["porristas"] = $porristas;
        }
    }

}