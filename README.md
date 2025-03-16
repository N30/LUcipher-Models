PUBLISH CONFIG:


php artisan vendor:publish --provider="All1\LuModels\Providers\LuModelsServiceProvider" --tag="config"


<h1>Laravel Universal(created in php, html, etc...) Repo - Models Addon Package</h1>
**NOT READY JUST UPLOADED FOR INTERNAL USE AS A PROJECT DEPENDENCY... BUT KINDA READY, USELESS WITHOUT DOCUMENTATION.**<br>
Alright let me write a 30 second documentation. Upon install package can read all existing models and create fully functioning CRUD routes using similar to Laravel Resource routes at:<br><br>
/api/{model}/{action}/{id?}<br>
/rest/{model}/{action}/{id?}<br>
/web/{model}/{action}/{id?}<br>
/spa/{model}/{action}/{id?} LIVEWIRE!<br>
/pwa/ -- coming soon.<br><br>
/app/ -- requires NativePhp and LUcipher-Eloquent-Datanase-API-Driver pacakge<br>
You'll see fully disfuncitonal blade UI but everything can be overrided and the idea of the package is to hit the ground running and override things as needed package checks app folder for custom routes, controllers, view files, policies, actions (laravel actinos), verbs (laravel verbs) otherwise uses default. It will eventually look good but I haven't spent anytime on UI.<br>
<br>
Laravel Universal open-sourced package with automatic API, HTTP, Livewire Controllers, Laravel Actions, Verbs, Policy Support. Increasing development time by automating CRUD operations from Modern SPA like components down to fully functioning API under the hood. The package automatically builds APIs from models, utilizes the API to build traditional HTTP controller, and then builds the Livewire using the same components including views.


*****DIFFERENCES BETWEEN USING PACKAGE WITH MODELS EXTENDING LuModel VS NOT ****
Theres more but I started writing his 80% towards the end so strting with samll stuff at end:
#for the most part it was built to ensure complex funcionality when it was necessary but package was built trying to need
that dependency at all....
1-LuModels integrates its own CRUD+I createFromData($this, $data, $controller_type); which doesn't really do ANYTHING cause
APIController calls it if its a regular model in the servie where the code actually happens.
2-Has helper functions that helps optimization and less database query calls and more control see config and model stub
    -getModelRelationships function helps better more acccurate funcionality
    -Other than that it is pretty pointless all meaningful attributes can be dfined in larave models too
    -Even the functions could have been created in a service provider 
    -hopefully down the road with trains and stuff it will become actully helpful
***IMportant: Still if Lu package is used always use it by making base model that extends it, IT WILL BECOME USEFULL IN FUTURE!


The purpose of the php file Events is just in case they ever become useful but the app only needs to listed and implement listeners listening to {model}.created and {model}.updated and {model}.deleted events. CRUD  for example user.creted:

protected $listen = [
    'user.created' => [
        \App\Listeners\SendActivationEmail::class,
        \App\Listeners\LogUserActivation::class,
    ],
];