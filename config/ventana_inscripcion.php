<?php
// helpers/ventana_delegados_2026.php

function checkVentanaDelegados2026(int $asoAgenId): array
{
    $tz  = new DateTimeZone('America/Bogota');
    $now = new DateTimeImmutable('now', $tz);

    $ini_5 = new DateTimeImmutable('2026-02-05 08:00:00', $tz);
    $fin_5 = new DateTimeImmutable('2026-02-05 16:00:00', $tz);

    $ini_6 = new DateTimeImmutable('2026-02-06 08:00:00', $tz);
    $fin_6 = new DateTimeImmutable('2026-02-06 16:00:00', $tz);

    // Día 5: nacional
    if ($now >= $ini_5 && $now <= $fin_5) {
        return [
            'ok'   => true,
            'code' => 'OK',
            'msg'  => 'Inscripción habilitada (Jueves 5: nacional).'
        ];
    }

    // Día 6: solo agencia 101
    if ($now >= $ini_6 && $now <= $fin_6) {
        if ($asoAgenId === 101) {
            return [
                'ok'   => true,
                'code' => 'OK',
                'msg'  => 'Inscripción habilitada (Viernes 6: Cundinamarca).'
            ];
        }
        return [
            'ok'   => false,
            'code' => 'FUERA_DE_CUNDINAMARCA',
            'msg'  => 'El viernes 6 de febrero la inscripción está habilitada únicamente para Cundinamarca (agencia 101).'
        ];
    }

    return [
        'ok'   => false,
        'code' => 'FUERA_DE_HORARIO',
        'msg'  => 'Inscripción cerrada. Horario: 5-feb-2026 (nacional) 08:00 a 16:00 y 6-feb-2026 (solo Cundinamarca / agencia 101) 08:00 a 16:00.'
    ];
}
