# Stud.IP-SpeechToTextPlugin

## Installation

Das Plugin bietet mit dem Interface `SpeechToTextPlugin\Contracts\Services\PredictionServiceInterface` die Möglichkeit,
unterschiedlichste Transkriptions-Backends zu nutzen.

Der aktuelle Default ist, über Replicate das Model https://replicate.com/vaibhavs10/incredibly-fast-whisper zu
verwenden. Dazu muss aber die Environment-Variable `REPLICATE_TOKEN` gesetzt werden, da sonst kein Zugriff auf Replicate
erfolgen kann. Das Token erhält man unter https://replicate.com/account/api-tokens
