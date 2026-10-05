# Changelog

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
