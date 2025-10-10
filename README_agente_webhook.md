## Webhook do Agente – Guia Rápido de Uso

Este documento explica como criar um agente, enviar mensagens pelo webhook, usar limites de caracteres, permissões de entrada/saída e encerrar um atendimento.

---
## 1. Criar um Agente
Endpoint: `POST /agente/criar_agente`

Campos obrigatórios:
- `nome_agente` (string)
- `instrucoes` (string) – contexto/sistema
- `modelo` (string) – ex.: `gpt-4o-mini`, `gemini-2.0-flash`
- `ia` (string) – `ChatGPT` ou `Gemini` (depende de como foi salvo na sua base)
- `chave_api` (string)
- `dominios_permitidos` (array ou string separada por vírgulas) – controle de origem (CORS lógico)
- `permissoes_entrada` (array de objetos)
- `permissoes_saida` (array de objetos)

Campos opcionais úteis:
- `status` ("ativo" | "pausa") – default: `ativo`
- `links_anexos` (array de URLs)
- `limite_caracter` (int > 0) – limita o tamanho do texto de ENTRADA enviado pelo cliente para o modelo.

Exemplo (JSON):
```json
{
	"nome_agente": "Suporte Nível 1",
	"instrucoes": "Responda de forma cordial e objetiva.",
	"modelo": "gpt-4o-mini",
	"ia": "ChatGPT",
	"chave_api": "SUA_CHAVE",
	"dominios_permitidos": ["example.com", "meusite.com"],
	"permissoes_entrada": [
		{"nome": "Áudio", "valor": true}
	],
	"permissoes_saida": [
		{"nome": "Links", "valor": true},
		{"nome": "Imagem", "valor": true},
		{"nome": "Documentos", "valor": true}
	],
	"limite_caracter": 500
}
```
Resposta (201):
```json
{ "msg": "Agente cadastrado com sucesso", "id": "<uuid>", "url": "/agente/webhook/<uuid>" }
```

---
## 2. Protocolo de Atendimento
Cada conversa exige um `protocolo` (string) gerado pelo seu sistema (UUID ou similar). Use sempre o mesmo protocolo enquanto a conversa estiver aberta. Ao encerrar, não reutilize.

---
## 3. Enviar Mensagem ao Webhook
Endpoint: `POST /agente/webhook/{agente_id}`

Campos principais do corpo:
- `protocolo` (string) – OBRIGATÓRIO.
- `texto` (string) – Conteúdo da mensagem do usuário (opcional se estiver enviando áudio ou somente encerrando).
- `audio` (string/base64) – Opcional (se permitido). Quando enviado, o endpoint retorna apenas a transcrição e NÃO aciona a IA.
- `imagem` / `arquivo` – Opcional (conforme futuras extensões).
- `encerrar_atendimento` (boolean) – Se `true`, encerra a conversa e retorna confirmação.

Campos de identificação do cliente (todos OPCIONAIS – não são obrigatórios):
- `nome_usuario`
- `email`
- `contato`

Exemplo mínimo (mensagem de texto):
```json
{
	"protocolo": "123e4567-conv-01",
	"texto": "Olá, preciso de ajuda com meu pedido"
}
```

Exemplo com metadados de cliente (lembrando: NÃO obrigatórios):
```json
{
	"protocolo": "123e4567-conv-01",
	"texto": "Quero segunda via da fatura",
	"nome_usuario": "João Silva",
	"email": "joao@example.com",
	"contato": "+55 11 99999-0000"
}
```

Resposta típica (200):
```json
{
	"agente_id": "...",
	"protocolo": "123e4567-conv-01",
	"conteudo_recebido": {
		"texto": "Quero segunda via da fatura",
		"usuario": {"nome": "João Silva", "email": "joao@example.com", "contato": "+55 11 99999-0000", "protocolo": "123e4567-conv-01"},
		"limite_caracter": 500
	},
	"mensagem": "Aqui está o procedimento...",
	"anexos": ["https://exemplo.com/manual.pdf"],
	"modelo": "gpt-4o-mini",
	"audio_transcrito": null
}
```

### Quando há truncamento por limite
O campo `limite_caracter` é definido NO AGENTE (não é um limite do usuário). Ele representa:
1. Quantos caracteres do texto de ENTRADA (mensagem do usuário) serão encaminhados ao modelo.
2. Uma instrução para o modelo tentar não ultrapassar esse tamanho na RESPOSTA (melhor-esforço – não é corte rígido na saída neste momento).

Se o agente foi configurado com `limite_caracter = 100` e o usuário envia uma mensagem de 300 caracteres:
```json
"conteudo_recebido": {
  "texto": "<primeiros 100 chars>",
  "truncado": true,
  "limite_caracter": 100,
  "usuario": { ... }
}
```

#### Escopo atual da implementação
| Aspecto | Implementado? | Detalhe |
|---------|---------------|---------|
| Corte (hard) do texto de entrada excedente | Sim | Feito no webhook antes de chamar o modelo. |
| Instrução para o modelo limitar tamanho da resposta | Sim | Inserida no prompt (`regra_limite`). |
| Corte (hard) automático da resposta do modelo | Não (ainda) | Pode ser adicionado se desejar truncar também a saída. |

Se quiser ativar também truncamento rígido da resposta, peça e podemos implementar (ex.: cortar `mensagem` final e marcar `output_truncado: true`).

---
## 4. Enviar Áudio para Transcrição
Se `permissoes_entrada` inclui `{ "nome": "Áudio", "valor": true }`:
```json
{
	"protocolo": "123e4567-audio-01",
	"audio": "<base64-do-audio>"
}
```
Resposta:
```json
{ "audio_transcrito": "Texto transcrito do áudio" }
```
Observação: O áudio NÃO gera resposta da IA automaticamente – só transcreve.

---
## 5. Encerrar Atendimento
```json
{
	"protocolo": "123e4567-conv-01",
	"encerrar_atendimento": true
}
```
Resposta:
```json
{ "msg": "Atendimento encerrado com sucesso.", "protocolo": "123e4567-conv-01", "fim": "2025-09-29T12:34:56-03:00" }
```

Após encerrar, novas mensagens com o mesmo protocolo retornam erro.

---
## 6. Permissões de Entrada e Saída
### Entrada (`permissoes_entrada`)
Exemplo:
```json
[{"nome": "Áudio", "valor": true}]
```
Se áudio não estiver permitido → retorna `403` ao tentar enviar.

### Saída (`permissoes_saida`)
Controla o que o modelo pode retornar (pós-processamento):
- Nomes que contenham: `Links` / `URL` → bloqueiam todos os links.
- `Imagem` → remove URLs com extensões de imagem (png, jpg, jpeg, gif, bmp, webp, svg).
- `Documentos` → remove URLs de documentos (pdf, txt, doc, docx, xls, xlsx, csv, ppt, pptx).
- `Anexos` / `Attachment` → limpa toda lista de anexos.

Se algo for removido, a resposta inclui:
```json
{
	"saida_filtrada": true,
	"motivos_filtragem": ["links removidos por permissão"],
	"imagens_removidas": ["..."],
	"documentos_removidos": ["..."]
}
```

---
## 7. Limite de Caracteres (`limite_caracter`)
- Define o máximo de caracteres aceitos do campo `texto` de entrada.
- Se exceder: o texto é cortado e marcado com `truncado: true`.
- O prompt do modelo recebe instrução para NÃO ultrapassar esse limite na resposta.

---
## 8. Listar e Obter Dados
Listar agentes: `GET /agente/listar_agentes`
Obter um agente: `GET /agente/obter_agente/{agente_id}`
Listar atendimentos: `GET /agente/listar_atendimentos`

---
## 9. Erros Comuns
| Situação | Código HTTP | Campo `error` / Observação |
|----------|-------------|----------------------------|
| Agente não encontrado | 404 | `{"error": "Agente não encontrado"}` |
| Protocolo ausente | 400 | `{"error": "Protocolo obrigatório..."}` |
| Atendimento já encerrado | 403 | Conversa não aceita mais mensagens |
| Domínio não permitido | 403 | Origem não consta em `dominios_permitidos` |
| Áudio sem permissão | 403 | Permissão de entrada não liberada |
| Erro no provedor IA | 5xx/4xx | Agente é pausado automaticamente |

Quando ocorre erro de provedor: resposta inclui `error_code`, `error_code_id`, `sugestao` e o agente muda para status `pausa`.

---
## 10. Exemplo cURL (Texto)
```bash
curl -X POST \
	-H "Content-Type: application/json" \
	-H "Origin: https://example.com" \
	http://localhost:5000/agente/webhook/<AGENTE_ID> \
	-d '{
		"protocolo": "test-prot-001",
		"texto": "Olá, preciso de suporte",
		"nome_usuario": "Maria"
	}'
```

## 11. Exemplo cURL (Encerrar)
```bash
curl -X POST -H "Content-Type: application/json" \
	http://localhost:5000/agente/webhook/<AGENTE_ID> \
	-d '{"protocolo": "test-prot-001", "encerrar_atendimento": true}'
```

---
## 12. Campos Opcionais (Resumo)
Os seguintes campos NÃO são obrigatórios e podem ser omitidos sem erro:
- `nome_usuario`, `email`, `contato`
- `texto` (quando enviar somente áudio ou encerrar)
- `audio` (somente se não for usar voz)
- `links_anexos`
- `limite_caracter`
- `status` (default `ativo`)

---
## 13. Boas Práticas
- Gere protocolos únicos (UUID) para cada novo atendimento.
- Pause e revise o agente se houver muitos `error_code` de provedor.
- Use `limite_caracter` para reduzir custo e latência se usuários enviarem textos muito longos.
- Monitore `saida_filtrada` para auditar remoções.

---
## 14. FAQ Rápido
1. Posso mandar só áudio? Sim – retorna somente a transcrição.
2. Campos do cliente são obrigatórios? Não, todos opcionais.
3. Posso reutilizar protocolo encerrado? Não recomendado; gere outro.
4. Por que veio `saida_filtrada`? Permissões de saída bloquearam algo.
5. Como reativar agente pausado? Edite o agente e defina `status` = `ativo`.

---
Se precisar de exemplos adicionais ou testes automatizados, peça e adicionamos aqui. ✅

