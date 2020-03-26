<?php

namespace App\Http\Controllers;

use ConfigCat\ConfigCatClient;
use ConfigCat\User;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /**
     * @var ConfigCatClient
     */
    private $configCatClient;

    public function __construct(ConfigCatClient $configCatClient)
    {
        $this->configCatClient = $configCatClient;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function isAwesomeEnabled()
    {
        return response()->json($this->configCatClient->getValue("isAwesomeFeatureEnabled", false));
    }

    public function isPOCEnabled(Request $request)
    {
        return response()->json($this->configCatClient->getValue("isPOCFeatureEnabled", false, new User("#SOME-USER-ID#", $request->email)));
    }
}
