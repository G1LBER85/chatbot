<?php
/**
 * Configuración y funciones de Redis con Predis
 */

// __DIR__ hace que funcione aunque se incluya desde panel/
require_once __DIR__ . '/vendor/autoload.php';

use Predis\Client;

class RedisCache {
    private $redis = null;

    public function __construct() {
        try {
            $cliente = new Client([
                'scheme' => 'tcp',
                'host'   => '127.0.0.1',
                'port'   => 6379,
            ]);

            // Verificar conexión
            $cliente->ping();
            $this->redis = $cliente;
        } catch (Exception $e) {
            // Si Redis está apagado, $this->redis queda en null y
            // los métodos devuelven false en vez de tronar la página
            error_log("Redis connection error: " . $e->getMessage());
        }
    }

    /**
     * ¿Hay conexión con Redis?
     */
    public function disponible() {
        return $this->redis !== null;
    }

    /**
     * Guardar Chat ID en caché (expira en 30 días)
     */
    public function setChatId($curp, $chat_id) {
        if (!$this->redis) return false;
        return $this->redis->setex("chatid:$curp", 2592000, $chat_id);
    }

    /**
     * Obtener Chat ID del caché
     */
    public function getChatId($curp) {
        if (!$this->redis) return null;
        return $this->redis->get("chatid:$curp");
    }

    /**
     * Guardar respuesta en caché (expira en 24 horas)
     */
    public function setRespuesta($numero, $datos) {
        if (!$this->redis) return false;
        return $this->redis->setex("respuesta:$numero", 86400, json_encode($datos));
    }

    /**
     * Obtener respuesta del caché
     */
    public function getRespuesta($numero) {
        if (!$this->redis) return null;
        $data = $this->redis->get("respuesta:$numero");
        return $data ? json_decode($data, true) : null;
    }

    /**
     * NUEVO: borrar una respuesta del caché
     * (se llama al guardar/editar una opción en el panel)
     */
    public function borrarRespuesta($numero) {
        if (!$this->redis) return false;
        return $this->redis->del("respuesta:$numero");
    }

    /**
     * Agregar mensaje a la cola
     */
    public function agregarACola($mensaje) {
        if (!$this->redis) {
            error_log("Redis no disponible: mensaje NO encolado - " . json_encode($mensaje));
            return false;
        }
        return $this->redis->lpush("cola:mensajes", json_encode($mensaje));
    }

    /**
     * Obtener siguiente mensaje de la cola
     */
    public function obtenerDeCola() {
        if (!$this->redis) return null;
        $mensaje = $this->redis->rpop("cola:mensajes");
        return $mensaje ? json_decode($mensaje, true) : null;
    }

    /**
     * Cantidad de mensajes en cola
     */
    public function tamanoCola() {
        if (!$this->redis) return 0;
        return $this->redis->llen("cola:mensajes");
    }

    /**
     * Limpiar cachés (respuestas y chat IDs)
     * CORRECCIÓN: antes usaba flushdb(), que también borraba la cola
     * de mensajes pendientes. Ahora solo borra las llaves de caché.
     */
    public function limpiarCache() {
        if (!$this->redis) return false;
        $llaves = array_merge(
            $this->redis->keys("respuesta:*"),
            $this->redis->keys("chatid:*")
        );
        return $llaves ? $this->redis->del($llaves) : 0;
    }

    /**
     * Cerrar conexión
     * CORRECCIÓN: Predis usa disconnect(), no close()
     */
    public function cerrar() {
        if ($this->redis) {
            $this->redis->disconnect();
        }
    }
}

// Crear instancia global
$redis = new RedisCache();