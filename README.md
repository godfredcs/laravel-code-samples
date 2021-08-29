## LARAVEL CODE SAMPLES

Hi Robert,

I have in this repository a few code samples that I would like to share with you.

I have a sample **abstract service** `App\Services\Service` that is extended by other services that are used by the respective _controllers_. This is done to keep both _controllers_ and _services_ as lean as possible. I added `App\Services\LocalAuthorityService` and `App\Http\Controllers\LocalAuthoritiesController` as examples of how the service is used.
I have also added an `App\Support\XmlParserService` code that I wrote for parsing xml.
