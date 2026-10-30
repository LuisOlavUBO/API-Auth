# Spec Delta

## MODIFIED Requirements

### Requirement: Vigencia de los tokens
Los access tokens de usuario SHALL vencer por defecto a los 60 minutos y los refresh tokens a los 120 minutos, que se renuevan cada vez que se usan y por eso equivalen a un plazo de inactividad. Ambos plazos MUST poder configurarse por entorno. Los access tokens SHALL ser JWT firmados con la clave privada del servicio.

#### Scenario: Vigencia por defecto
- **WHEN** se emite un par de tokens sin configuración de vigencia propia
- **THEN** `expires_in` corresponde a 60 minutos y el refresh token es válido durante 120 minutos

#### Scenario: El refresh se renueva con el uso
- **WHEN** se renueva la sesión con un refresh token vigente
- **THEN** el refresh token nuevo vuelve a ser válido durante 120 minutos
