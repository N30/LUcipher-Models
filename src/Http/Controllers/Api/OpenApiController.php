<?php

namespace All1\LuModels\Http\Controllers\Api;

use All1\LuModels\Support\OpenApi;
use Illuminate\Http\JsonResponse;

/** GET /api/openapi.json: the OpenAPI 3.1 document of the exposed models (lu::api.openapi). */
class OpenApiController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(OpenApi::document(), 200, [], JSON_UNESCAPED_SLASHES);
    }
}
