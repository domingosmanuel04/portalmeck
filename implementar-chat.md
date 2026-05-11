# Implementação do Chat Interno

## Fases de Implementação

1. **Backend - Rotas e Lógica de Base de Dados (API)**
   - `api/chat_contacts.php`: Obter a lista de contactos (todos os utilizadores).
   - `api/chat_messages.php`: Ler o histórico de mensagens entre o utilizador atual (em sessão) e o contacto selecionado.
   - Atualizar `api/chat_send.php`: Ajustar e validar o código existente para garantir o retorno adequado em JSON ao submeter uma nova mensagem, lidando adequadamente com os IDs e textos.
   
2. **Frontend - Adaptação Visual e Integração na Interface (`chat.php`)**
   - Ligar a gestão de estado ao interface via Alpine.js (ou Vanilla JS) presente no boilerplate do projeto.
   - Programar o fetch de Contactos via API e substituir os "mocks" visuais.
   - Programar a seleção de um contacto (guardando o ID desse contacto na memória do frontend).
   - Programar a leitura do histórico de mensagens ao selecionar esse contacto e substituir os "mocks".
   - Integrar o "Poling" (refresh das mensagens a cada 3 a 5 segundos) quando um contacto está aberto.
   - Criar o evento associado ao botão/input de "Enviar", fazendo um POST para a nossa API.
   - Fazer o scroll automático para o final das mensagens quando novas mensagens chegam ou são enviadas.

3. **Validações e Testes Finais**
   - Garantir que a tabela `chat_messages` está a ser criada de forma automática no envio da primeira mensagem ou ao visitar o endpoint (já existe   no chat_send.php a criação da table SE não existir, podemos abstrair).
   - Validar problemas de CORS, Content-Type ou bloqueios de sessão na API.
