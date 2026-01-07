<?php

declare(strict_types=1);

namespace NFSeNacional\Validator;

enum SchemaType: string
{
    case DPS = 'EnviarDpsEnvio';
    case LOTE = 'EnviarLoteRpsEnvio';
}
