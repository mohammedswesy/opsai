# AI

No AI provider is called in the current build.

`OPENAI_API_KEY`, `OPENAI_MODEL`, and `AI_PROVIDER` are reserved in `.env.example` and read into `config/services.php`. They are not sent to the browser.

Later phases will add an `AIProviderInterface`, validate structured output, and let the model call application services only after authorization. That work starts after customers, requests, and work orders exist.
