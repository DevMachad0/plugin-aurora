# Aurora Chat

Plugin WordPress para criação de agentes conversacionais com templates visuais personalizados.

## Recursos

- Área administrativa única com abas para **Agentes**, **Templates** e **Mensagens**.
- Cadastro rápido de agentes com integração de API, limite de interações e opção de formulário de atendimento.
- Geração automática de shortcode para cada agente (`[aurora_chat id="123"]`).
- Dois templates prontos:
  - **Sessão**: experiência similar ao ChatGPT com layout amplo.
  - **Balão de diálogo**: widget flutuante inspirado em assistentes pop-up.
- Registro de mensagens para auditoria e ajuste de performance.

## Instalação

1. Copie a pasta do plugin para `wp-content/plugins/aurora-chat`.
2. Ative o plugin no painel do WordPress.
3. Acesse *Chat Aurora* no menu lateral do WordPress para configurar agentes e templates.

## Utilização

1. Crie um agente fornecendo nome, endpoint da API e template.
2. Copie o shortcode gerado e cole em qualquer página ou post.
3. Personalize templates existentes ou crie novos através do editor padrão do WordPress.

## Desenvolvimento

- Arquivos PHP principais em `includes/`.
- Recursos visuais em `assets/css` e `assets/js`.
- Templates base em `templates/`.

Contribuições são bem-vindas! Abra um _pull request_ com melhorias ou correções.
