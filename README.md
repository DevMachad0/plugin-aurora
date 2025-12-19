# Aurora Chat 💬

Plugin WordPress para integração de agentes conversacionais com IA, oferecendo templates visuais personalizados e experiência de chat moderna.

## Índice

- [Recursos](#recursos)
- [Instalação](#instalação)
- [Guia de Uso Prático](#guia-de-uso-prático)
  - [1. Acessando o Painel](#1-acessando-o-painel)
  - [2. Configurando Templates](#2-configurando-templates)
  - [3. Criando um Agente](#3-criando-um-agente)
  - [4. Adicionando o Chat ao seu Site](#4-adicionando-o-chat-ao-seu-site)
  - [5. Personalizando Mensagens](#5-personalizando-mensagens)
- [Templates Disponíveis](#templates-disponíveis)
- [Configurações Avançadas](#configurações-avançadas)
- [Funcionalidades](#funcionalidades)
- [Estrutura de Arquivos](#estrutura-de-arquivos)
- [FAQ](#faq)

---

## Recursos

- ✅ **Área administrativa completa** com 3 abas: Agentes, Templates e Mensagens
- ✅ **Dois templates visuais** prontos para uso: Sessão (tela cheia) e Balão de Diálogo (pop-up flutuante)
- ✅ **Shortcode simples** para inserir o chat em qualquer página: `[aurora_chat id="123"]`
- ✅ **Tema claro/escuro** com alternância automática
- ✅ **Gravação de áudio** com transcrição automática via IA
- ✅ **Limite de interações** configurável por agente
- ✅ **Limite de caracteres** por mensagem do usuário
- ✅ **Mensagens personalizáveis** (boas-vindas, erros, status)
- ✅ **Registro de conversas** para auditoria
- ✅ **Integração via Webhook** com o Sistema Aurora

---

## Instalação

### Passo 1: Instalar o Plugin
1. Copie a pasta `aurora-chat` (ou o arquivo `.zip`) para `wp-content/plugins/`
2. Acesse o painel do WordPress em **Plugins**
3. Localize "Aurora Chat" e clique em **Ativar**

### Passo 2: Verificar Ativação
Após ativar, você verá um novo item **Chat Aurora** no menu lateral do WordPress.

---

## Guia de Uso Prático

### 1. Acessando o Painel

1. No WordPress, vá para **Chat Aurora** no menu lateral
2. Você verá três abas principais:
   - **Agentes**: Crie e gerencie seus chatbots
   - **Templates**: Escolha o visual do chat
   - **Mensagens**: Personalize textos exibidos no chat

---

### 2. Configurando Templates

Antes de criar um agente, verifique os templates disponíveis:

1. Clique na aba **Templates**
2. Você verá os templates pré-instalados:
   - **Sessão**: Layout amplo, similar ao ChatGPT
   - **Balão de Diálogo**: Widget flutuante no canto da página
3. Clique em **Pré-visualizar** para ver como cada um ficará no seu site
4. Os templates incluem alternância de tema claro/escuro

> 💡 **Dica**: Se os templates não aparecerem, clique em "Restaurar templates padrão" na parte inferior da página.

---

### 3. Criando um Agente

Para criar um novo chatbot:

1. Vá para a aba **Agentes**
2. Preencha o formulário "Conectar agente existente":

| Campo | Descrição | Exemplo |
|-------|-----------|---------|
| **Nome do agente** | Nome de identificação do chatbot | `Suporte ao Cliente` |
| **URL do Webhook** | URL completa fornecida pelo Sistema Aurora | `https://api.aurora.com/agente/webhook/abc-123` |
| **Template visual** | Escolha Sessão ou Balão de Diálogo | `Sessão` |
| **Limite de interações** | Máximo de mensagens por sessão (0 = ilimitado) | `50` |
| **Limite de caracteres** | Máximo de caracteres por mensagem (0 = ilimitado) | `500` |
| **Formulário de atendimento** | Coletar dados do usuário antes do chat | `Não` |

3. Clique em **Criar agente**
4. O shortcode será gerado automaticamente: `[aurora_chat id="XXX"]`

---

### 4. Adicionando o Chat ao seu Site

Após criar o agente, adicione o chat em qualquer página ou post:

#### Método 1: Editor de Blocos (Gutenberg)
1. Edite a página desejada
2. Adicione um bloco "Shortcode" ou "HTML personalizado"
3. Cole o shortcode: `[aurora_chat id="123"]`
4. Publique ou atualize a página

#### Método 2: Editor Clássico
1. No editor de texto, cole o shortcode onde deseja que o chat apareça
2. Salve a página

#### Método 3: Arquivo PHP do Tema
```php
<?php echo do_shortcode('[aurora_chat id="123"]'); ?>
```

> ⚠️ **Importante**: Substitua `123` pelo ID real do seu agente, mostrado na tabela de agentes cadastrados.

---

### 5. Personalizando Mensagens

Customize todos os textos exibidos no chat:

1. Vá para a aba **Mensagens**
2. Configure os campos disponíveis:

| Campo | Descrição | Padrão |
|-------|-----------|--------|
| **Título de boas-vindas** | Exibido no topo do balão | `Bem-vindo` |
| **Subtítulo de boas-vindas** | Texto complementar | `Estamos aqui para ajudar!` |
| **Mensagem inicial do bot** | Primeira mensagem do assistente | `Olá! Sou o Aurora...` |
| **Mensagem de erro** | Quando há falha na comunicação | `Não foi possível obter resposta...` |
| **Limite atingido** | Quando acabam as interações | `O limite de interações foi atingido.` |
| **Status Online/Offline** | Indicador de disponibilidade | `Online` / `Offline` |
| **Respondendo** | Durante processamento | `Respondendo…` |
| **Concluído** | Após resposta (use %s para tempo) | `Resposta em %ss` |
| **Encerramento** | Mensagem de despedida | `Atendimento encerrado com sucesso.` |
| **Texto do botão** | Label do botão flutuante (máx. 25 caracteres) | `Fale com a Aurora` |

3. Clique em **Salvar mensagens**

---

## Templates Disponíveis

### Template Sessão

Interface semelhante ao ChatGPT com:
- Header com avatar e status do agente
- Área de mensagens com rolagem suave
- Campo de entrada com botão de microfone
- Alternador de tema claro/escuro
- Footer com créditos

**Ideal para**: Páginas dedicadas de suporte, landing pages, FAQs interativos.

### Template Balão de Diálogo

Widget flutuante com:
- Botão de abertura no canto inferior
- Painel expansível com chat completo
- Tela de boas-vindas inicial
- Suporte a gravação de áudio
- Overlay para foco no chat

**Ideal para**: Todas as páginas do site, e-commerce, blogs.

---

## Configurações Avançadas

### Editando um Agente Existente

1. Na tabela de agentes, clique em **Editar** ao lado do agente desejado
2. Você será levado à tela de edição do WordPress
3. No metabox "Configurações do agente", ajuste:
   - URL do Webhook
   - Template visual
   - Limite de interações
   - Limite de caracteres
   - Formulário de atendimento
4. Clique em **Atualizar**

### Copiando o Shortcode

Na tabela de agentes:
1. Localize a coluna **Shortcode**
2. Clique no botão **Copiar** ao lado do código
3. Cole onde desejar no seu site

---

## Funcionalidades

### Gravação de Áudio 🎤

O chat suporta entrada por voz:
1. O usuário clica no ícone do microfone
2. Grava sua mensagem
3. O áudio é enviado ao servidor para transcrição
4. O texto transcrito é enviado ao agente

> 📝 **Nota**: A transcrição usa o serviço de IA configurado no Sistema Aurora.

### Alternância de Tema 🌓

Ambos os templates suportam tema claro e escuro:
- Botão de alternância no header do chat
- Persiste apenas durante a sessão
- CSS pronto em `assets/css/dark.css`

### Formatação de Mensagens

O bot pode enviar respostas com:
- **Markdown** (listas, negrito, itálico)
- **Blocos de código** com syntax highlighting
- **Links clicáveis** com pré-visualização
- **Áudio** reproduzível diretamente no chat

---

## Estrutura de Arquivos

```
aurora-chat/
├── index.php                 # Arquivo principal do plugin
├── README.md                 # Esta documentação
├── README_agente_webhook.md  # Documentação técnica da API
├── assets/
│   ├── css/
│   │   ├── admin.css         # Estilos do painel admin
│   │   ├── frontend.css      # Estilos principais do chat
│   │   └── dark.css          # Tema escuro
│   └── js/
│       ├── admin.js          # Scripts do painel admin
│       └── frontend.js       # Scripts do chat (AJAX, UI)
├── includes/
│   └── class-aurora-chat-plugin.php  # Classe principal
└── templates/
    ├── session.php           # Template Sessão
    └── bubble.php            # Template Balão de Diálogo
```

---

## FAQ

### O chat não aparece na página
- Verifique se o shortcode está correto: `[aurora_chat id="XXX"]`
- Confirme que o agente está publicado (não em rascunho)
- Verifique se há erros no console do navegador (F12)

### As mensagens não estão sendo enviadas
- Verifique se a URL do Webhook está correta
- Confirme que o domínio do seu site está permitido no Sistema Aurora
- Verifique a conexão com a internet

### O áudio não funciona
- Confirme que o navegador tem permissão para usar o microfone
- Verifique se está acessando o site via HTTPS (obrigatório para gravação)

### Como mudar as cores do chat?
- Edite o arquivo `assets/css/frontend.css`
- Para o tema escuro, edite `assets/css/dark.css`
- Use as CSS variables (ex.: `--aurora-color-primary`)

### Como integrar com meu próprio backend?
- Configure a URL do Webhook apontando para sua API
- Sua API deve seguir o protocolo documentado em `README_agente_webhook.md`
- Retorne as respostas no formato JSON esperado

### Posso usar múltiplos agentes no mesmo site?
- Sim! Crie quantos agentes precisar
- Cada um terá seu próprio shortcode
- Use shortcodes diferentes em páginas diferentes

---

## Suporte

Para dúvidas ou problemas:
- 📧 Contato: [agentesaurora.com.br](https://agentesaurora.com.br/)
- 📖 Documentação técnica: Consulte `README_agente_webhook.md`

---

**Versão atual**: 1.0.56  
**Autor**: Aurora Labs  
**Licença**: Proprietária
