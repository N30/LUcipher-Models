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
