# Changelog

## [1.5.4] - 2026-10-30

### Alterado
- Compromissos (e itens da conferência) por termo: o limite passa de 20 para **50**, com até 1.000 caracteres cada (#3). Antes, um item com mais de 255 caracteres era cortado sem aviso; agora a configuração avisa qual item passou do limite. O campo mostra os limites.

### Corrigido
- Erro interno do TCPDF (ex.: imagem corrompida) encerrava o PHP com `die()`: o número sequencial reservado se perdia, a resposta vinha em HTML em vez de JSON e, na assinatura pelo link, o pedido podia ficar como assinado sem documento. Agora o erro vira exceção e passa pelo mesmo tratamento das outras falhas ao gerar o PDF.
- Logo: imagens com mais de 3000 x 3000 pixels são recusadas. Um arquivo pequeno que declara dimensões enormes estourava a memória ao gerar o PDF, e o logo salvo fazia falhar todos os termos da empresa. Um logo assim salvo antes desta versão fica fora do PDF.
- Assinatura pelo link: o documento ficava com o colaborador como dono, porque o GLPI grava quem está logado. Agora o dono é quem emitiu o termo, como na assinatura na tela.
- PDF para assinar no papel: o histórico do computador registra cada PDF gerado e o código usado, para nenhum número sequencial ({seq}) sumir sem registro.
- Cabeçalho de controle de documentos: "Página X de Y" cabe na célula também com 10 páginas ou mais.
- Reenviar e cancelar termos pendentes usam o token CSRF de um formulário da página quando a meta `glpi:csrf_token` não existe.
- Contraste (WCAG AA): o título do quadro do termo deixa de usar o laranja do tema sobre fundo claro, e o texto de ajuda do quadro de assinatura ficou mais escuro.

## [1.5.3] - 2026-10-29

### Corrigido
- **Assinatura de quem emite o termo (TI):** a linha "Entregue por / Recebido por (TI)" saía só com o nome, sem assinatura. Agora cada técnico cadastra a própria assinatura (desenhada ou com o nome digitado) em **Ativos › Termos de Responsabilidade › Minha assinatura**, e ela vai em todo termo que ele gerar. O registro do PDF diz que ela foi aplicada a partir do cadastro, com o usuário do GLPI. Sem assinatura cadastrada, o registro diz quem emitiu o termo e com qual usuário. O termo guarda a assinatura do momento em que foi gerado.
- Configuração: salvar ou restaurar dava "A ação que você requisitou não é permitida" no GLPI 10 e 11 (#2). A 1.5.1 passou a validar o token CSRF de novo na página, mas o GLPI já valida e consome o token antes (`inc/includes.php` no 10, `CheckCsrfListener` no 11), então a segunda validação falhava sempre. O formulário usa só o token de `Html::closeForm()`, sem campo duplicado; a proteção CSRF do GLPI continua ativa. Correção para o GLPI 10 enviada por @Wellington-Leite (#1) e estendida ao GLPI 11.

## [1.5.2] - 2026-10-27

### Segurança
- GLPI 10: os valores gravados nas tabelas do plugin voltam a ser escapados. A 1.5.1 removeu o escape, e um apóstrofo nas observações ou nos dados do equipamento quebrava a consulta e permitia injeção de SQL no envio pelo link. O GLPI 11 não era afetado.
- GLPI 10: o que vai para o núcleo (usuário alternativo, status, documento e histórico) passa pela sanitização do GLPI, como a entrada de um formulário.
- Cancelar e assinar ao mesmo tempo não deixa mais o termo cancelado com documento arquivado, e uma falha ao arquivar não reabre um termo cancelado.

### Corrigido
- Assinatura com o nome digitado: usa só o nome digitado. Antes, com o campo vazio, a assinatura saía com o nome de quem já estava com o equipamento. A imagem leva só o nome (ajustando o tamanho para nomes longos), sem a chancela gerada no navegador, e o registro do PDF diz se a assinatura foi desenhada ou digitada.
- Quadro de assinatura: mantém fundo branco no modo escuro do GLPI para o traço continuar visível.
- Devolução à TI: limpa também o campo de usuário alternativo (`contact`) do computador, além do usuário vinculado.
- O comentário do documento e o histórico dizem "link de assinatura" quando o termo foi enviado por link copiado, e não mais "e-mail".
- Termos que aguardam assinatura são cancelados quando o mesmo computador recebe outro termo ou muda de ciclo de vida. Equipamento na lixeira ou modelo não aceita emissão nem assinatura.
- Somente ciclo de vida: avisa quando o GLPI recusa a alteração, em vez de responder sucesso.
- GLPI 10: nomes de usuário, status, entidade e computador com `& < >` não aparecem mais com entidades HTML no PDF e na aba.
- O PDF temporário usa um nome único, para dois termos do mesmo computador no mesmo segundo não trocarem de arquivo.
- `assetterms.xml`: compatibilidade com o GLPI 10 e 11, idiomas e descrição conferidos com o que o plugin faz, e capturas de tela novas.

## [1.5.1] - 2026-10-26

### Adicionado
- **Assinatura Eletrônica Digitada:** opção de assinar digitando o nome completo do usuário autenticado no GLPI, com seleção de estilos tipográficos e chancela eletrônica com trilha de auditoria (ID do usuário, IP, timestamp e conformidade com a Lei Federal nº 14.063/2020).
- **Recorte Automático de Assinatura (Bounding-Box Crop):** otimização da área útil da assinatura ao exportar para o PDF, garantindo nitidez e proporção adequada sem margens laterais vazias.
- **Suavização Vetorial Bézier:** interpolação de curvas suaves quadráticas no canvas manual de assinatura.
- **Suporte a Redimensionamento Dinâmico (ResizeObserver):** correção da inicialização do canvas ao alternar abas inativas ou monitores Retina/4K.

### Segurança
- Validação estrita de tokens CSRF em `front/config.php` e no endpoint de pré-visualização `ajax/preview.php`.
- O colaborador do termo (e o novo usuário em "Mover para outra pessoa") é conferido no servidor: precisa estar ativo, dentro da validade e ter perfil na entidade do computador (ou, recursivo, numa entidade acima), a mesma regra da lista da tela. O usuário que já está no equipamento continua aceito, para a devolução de quem foi desativado.
- PDF de exemplo da configuração: confere o acesso à entidade escolhida.
- Erros técnicos vão para o log `php-errors` do GLPI; a tela mostra apenas mensagem genérica.
- A imagem da assinatura é recusada se tiver dimensões acima de 4000 x 1500 pixels.

### Corrigido
- Autocriação de esquema (self-healing) para a tabela `glpi_plugin_assetterms_documents` na renderização da aba do computador.
- Remoção de escape redundante de dados em instalações GLPI 10.
- Arquivar o termo confere o vínculo do documento com o computador e a atualização do computador. Se algo falhar, o documento criado é apagado para evitar termos duplicados.
- Código sequencial (`{seq}`): o número de um termo não concluído retorna para o próximo termo.
- Desinstalar preserva a numeração do código sequencial.
- Histórico de termos da aba: os documentos gerados pelo plugin ficam registrados numa tabela própria, sem depender do nome do documento.
- Atualização do banco por versão (guardada na configuração do GLPI): cada migração roda uma única vez.
- Compatibilidade de cores com Dark Mode do GLPI 10 (Tabler UI / Auror).

### Alterado
- Instalação e desinstalação passaram para o `hook.php`, como o GLPI espera.
- Requisito de PHP declarado (7.4, o mínimo do GLPI 10.0).
- Modelo de tradução em `locales/assetterms.pot` (domínio `assetterms`).
- Verificação de sintaxe (`php -l`) no GitHub Actions com PHP 7.4, 8.1, 8.2 e 8.3; ações dos workflows fixadas por commit.

## [1.5.0] - 2026-10-05

### Adicionado
- **Modelo de cabeçalho "Controle de documentos" (ISO):**
  - tabela em todas as páginas com logo, tipo, código do formulário, título, nº da revisão, datas de emissão, última e próxima revisão e "Página X de Y";
  - rodapé com "Elaborado e revisado por" e "Aprovado por";
  - configurável por empresa, na tela de configuração;
  - o modelo "Simples" continua como padrão.
- Os campos do cabeçalho diminuem a fonte para caber numa linha.

## [1.4.0] - 2026-10-05

### Adicionado
- **Código do documento configurável** (Configurar › Plugins › Asset Terms):
  - formato com marcadores `{prefixo}`, `{ano}`, `{mes}`, `{dia}`, `{seq}`, `{tipo}` e `{aleatorio}`, por exemplo `{prefixo}-{ano}-{seq}` = TR-2026-000124;
  - número sequencial por empresa, com dígitos configuráveis, opção de recomeçar a cada ano e "Próximo número" para continuar uma numeração existente;
  - a numeração é atômica: termos criados ao mesmo tempo nunca recebem o mesmo número;
  - o padrão continua aleatório, como nas versões anteriores.
- **Aparência do documento:** logo (PNG ou JPG, até 1 MB) no topo do PDF, cor principal dos títulos e texto de rodapé em todas as páginas, ao lado do número da página.
- O PDF de exemplo da configuração mostra o código, o logo, a cor e o rodapé do formulário, sem consumir número.

### Alterado
- O código do documento pode ter até 100 caracteres.

## [1.3.0] - 2026-10-05

### Adicionado
- **Tela de configuração** (Configurar › Plugins › Asset Terms, só para administradores):
  - nome da empresa, CNPJ e cidade por entidade, no lugar do nome da entidade;
  - texto dos termos de entrega e de devolução editável (título, declaração, compromissos e parágrafo final), com os marcadores `{empresa}` e `{cnpj}`;
  - o plugin já vem com o texto padrão, com os botões "Restaurar padrão" e "Preencher com o texto padrão";
  - PDF de exemplo antes de salvar;
  - uma filial sem configuração própria usa a da entidade acima.
- Termos enviados para assinatura guardam o texto do momento do envio: o colaborador assina exatamente o que a TI mandou, mesmo que o texto mude depois.
- **Somente ciclo de vida › Mover para outra pessoa:** escolha um usuário do GLPI, digite um nome de preferência, ou os dois. O nome fica no campo "Usuário alternativo" do computador e identifica quem está com o equipamento quando a pessoa não tem usuário no GLPI.
- Botão "Configurar empresa e texto" em Ativos › Termos de Responsabilidade, para administradores.

### Corrigido
- GLPI 10: textos digitados com apóstrofo ou `< > &` (observações, dados do equipamento, nomes) apareciam com `\'` ou entidades HTML no PDF e no cadastro.
- Caixas de busca de usuário (Colaborador e Mover para outra pessoa) ocupam a largura do campo.

## [1.2.0] - 2026-10-05

### Adicionado
- **Menu Ativos › Termos de Responsabilidade:** o técnico escolhe o computador e já preenche o termo, sem abrir a ficha. A página também lista os termos que aguardam assinatura em todos os computadores.
- **Somente ciclo de vida:** muda só o status do equipamento (manutenção, empréstimo, descarte...), sem termo e sem colaborador. Pode manter ou remover o usuário, e a observação fica no histórico.
- **Gerar link de assinatura:** cria o termo pendente e mostra o link para o técnico mandar pelo Teams, WhatsApp ou chat. O colaborador entra no GLPI e assina, como no e-mail. Os pendentes ganham o botão "Copiar link".
- **Dados do equipamento no termo:** fabricante, modelo, tipo, processador, memória, disco e sistema operacional vêm do inventário e podem ser conferidos ou completados, com sugestões dos valores já cadastrados no GLPI. Muda só o termo, não o cadastro do computador.

### Alterado
- Sem o envio de e-mails do GLPI configurado, o botão de e-mail não aparece e o plugin orienta a usar o link. A cópia do PDF e o aviso ao técnico só são enviados quando há e-mail configurado.
- Acessórios: "Teclado externo" passou a "Teclado", e "Cabo de rede / trava de segurança" passou a "Trava de segurança".

## [1.1.0] - 2026-10-05

### Adicionado
- Envio do termo por e-mail: o colaborador recebe um link, entra no GLPI com o próprio usuário e assina na tela.
- Página de assinatura que exige login e só abre para o colaborador destinatário. Sem sessão, leva à tela de login e volta ao termo.
- Confirmação "li e concordo" antes da assinatura pelo link.
- O PDF registra a assinatura pelo link: data, hora, usuário do GLPI, IP e quem enviou o termo.
- Depois da assinatura: PDF arquivado, usuário e status do equipamento atualizados, cópia em PDF para o colaborador e aviso para quem enviou.
- Lista "Aguardando assinatura" na aba do computador, com opções para reenviar e cancelar.
- Tabela `glpi_plugin_assetterms_requests` para os termos enviados por e-mail.

### Alterado
- Endereços do plugin calculados pelo GLPI, o que também funciona com o plugin instalado pelo Marketplace no GLPI 10.

## [1.0.0] - 2026-10-05

### Adicionado
- Aba "Termo de Responsabilidade" nos computadores, com os dados do inventário.
- Termos de entrega e de devolução com textos próprios, iguais na tela e no PDF.
- Checklist de acessórios e campo de observações sobre o estado do equipamento.
- Assinatura na tela (toque, caneta ou mouse), também no celular.
- PDF de uma página arquivado na aba Documentos, com registro da assinatura (data, hora, técnico, IP) e código do documento.
- Atualização do usuário e do status do equipamento (Em uso / Em estoque) e registro no histórico.
- PDF para assinatura no papel, sem gravar nada.
- Linha do tempo dos termos gerados para o equipamento.
- Proteção CSRF, validação dos dados no servidor e conferência da imagem da assinatura.
- Compatível com GLPI 10.0.x e 11.0.x.
