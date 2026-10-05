# Asset Terms

**Termos de responsabilidade e ciclo de vida dos equipamentos no GLPI.**
- **Termo de entrega ou de devolução** gerado direto no GLPI. O colaborador assina na tela, na hora, ou depois pelo link enviado por **e-mail** ou por qualquer chat. O PDF fica arquivado no GLPI.
- **Mudança de status** (manutenção, empréstimo, descarte...) sem termo nenhum.

```
Ativos › Termos de Responsabilidade › [ NB-COLAB01            ▾ ]

  ( • ) Entrega ao colaborador   (   ) Devolução à TI   (   ) Somente ciclo de vida
  Colaborador: [ Maria Souza ▾ ]          Status após o termo: [ Em uso ▾ ]
  Acessórios:  [x] Fonte  [x] Cabo de força  [ ] Mochila  [ ] Mouse ...
  Equipamento: Dell Inc. | Latitude 5440 | Notebook | i5-1235U | 16 GB | 512 GB | Windows 11 Pro
  ┌──────────────────────────────────────────────┐
  │            ~ assinatura na tela ~            │
  └──────────────────────────────────────────────┘
  [ ✓ Gerar e arquivar ]  [ ✉ Enviar por e-mail ]  [ 🔗 Gerar link ]  [ 🖨 PDF para o papel ]
```

| Versão do GLPI | Suporte |
|---|---|
| **11.0.x** | ✅ |
| **10.0.x** | ✅ |

---

## Funcionalidades

- **Dois caminhos para o mesmo formulário:**
  - **Menu Ativos › Termos de Responsabilidade:** escolha o computador e preencha, sem abrir a ficha. A página também lista os termos que **aguardam assinatura** em todos os computadores;
  - **aba "Termo de Responsabilidade"** em cada computador.
- **Três ações:**
  - **entrega ao colaborador;**
  - **devolução à TI;**
  - **somente ciclo de vida:** muda só o status, sem termo. O equipamento pode manter a pessoa, ficar sem ninguém ou **passar para outra pessoa**: um usuário do GLPI ou um nome digitado, para quem não tem usuário.
- **Termos de entrega e de devolução** com textos próprios. O texto aparece na tela antes da assinatura e é o mesmo que vai para o PDF.
- **Tela de configuração para o administrador:**
  - nome da empresa, CNPJ e cidade por empresa (matriz e filiais);
  - o texto dos termos editável, com o texto padrão já preenchido e PDF de exemplo antes de salvar.
- **Dados do equipamento:**
  - fabricante, modelo, tipo, processador, memória, disco e sistema operacional vêm do inventário;
  - o técnico pode conferir ou completar cada campo, escolhendo entre os **valores já cadastrados no GLPI** ou digitando;
  - a alteração vale só para o termo, não para o cadastro do computador.
- **Checklist de acessórios** e campo de **observações** sobre o estado do equipamento.
- **Quatro formas de assinar:**
  1. **na tela, na hora:** com o dedo, uma caneta ou o mouse, no computador, no tablet ou no celular;
  2. **pelo e-mail:** o colaborador recebe um link, entra no GLPI com o próprio usuário, marca "li e concordo" e assina;
  3. **pelo link copiado:** a mesma coisa, mas o técnico manda o link pelo Teams, WhatsApp ou chat. Funciona **sem o e-mail do GLPI configurado**;
  4. **no papel:** PDF para imprimir, sem gravar nada.
- **PDF de uma página**, arquivado na aba **Documentos** do computador e aberto direto no navegador. Ele registra a data, a hora, o IP, o usuário do GLPI (na assinatura pelo link) e um **código do documento**.
- **O equipamento é atualizado sozinho:**
  - na entrega, o colaborador vira o usuário e o status passa a **Em uso**;
  - na devolução, o usuário fica vazio e o status passa a **Em estoque**;
  - na assinatura pelo link, a atualização só acontece depois que o colaborador assina;
  - tudo fica no **histórico**.
- **Termos aguardando assinatura** com os botões **Copiar link**, **Reenviar** (por e-mail) e **Cancelar**.
- **Seguro:**
  - só gera termos e muda status quem pode alterar o computador;
  - o envio é protegido pelo token CSRF do GLPI;
  - os dados são validados no servidor;
  - a assinatura é conferida como imagem PNG;
  - o link exige login no GLPI e só abre para o colaborador destinatário. Outro usuário logado não vê nem assina o termo.
- **Os PDFs ficam na aba Documentos do GLPI:** remover o plugin não apaga nenhum termo assinado.

---

## Instalação

> O instalador automático [Install-Gm](https://github.com/GustavoMS0/Install-Gm) já baixa e ativa o plugin. Se você usou o Install-Gm, pule esta parte.

### Pelo terminal (Linux)

No servidor do GLPI, rode os comandos abaixo. Eles baixam a última versão, colocam o plugin na pasta do GLPI, instalam e ativam.

```bash
# Pasta onde o GLPI está instalado (ajuste se for outra, ex.: /var/www/html/glpi)
GLPI_DIR=/var/www/glpi

# Ferramentas usadas para baixar e extrair
sudo apt-get install -y curl unzip      # Debian/Ubuntu (no RHEL/Rocky: sudo dnf install -y curl unzip)

# Baixa a última versão publicada
cd /tmp
URL=$(curl -fsSL https://api.github.com/repos/GustavoMS0/GLPI-AssetTerms/releases/latest \
      | grep -o 'https://[^"]*/assetterms-[^"]*\.zip' | head -n1)
curl -fsSL -o assetterms.zip "$URL"

# Coloca na pasta de plugins do GLPI
sudo rm -rf "$GLPI_DIR/plugins/assetterms"
sudo unzip -q assetterms.zip -d "$GLPI_DIR/plugins/"
sudo chown -R root:root "$GLPI_DIR/plugins/assetterms"
sudo chmod -R u=rwX,go=rX "$GLPI_DIR/plugins/assetterms"

# Instala, ativa e limpa o cache do GLPI
cd "$GLPI_DIR"
sudo -u www-data php bin/console plugin:install --username=glpi assetterms
sudo -u www-data php bin/console plugin:activate assetterms
sudo -u www-data php bin/console cache:clear
```

Depois, entre no GLPI. O menu **Ativos › Termos de Responsabilidade** já aparece.

> - **CentOS, Rocky ou RHEL:** o usuário do servidor web é `apache`, não `www-data`. Troque `sudo -u www-data` por `sudo -u apache`.
> - **`--username=glpi`:** é o login de um administrador do GLPI. Troque se o seu administrador tiver outro nome.

Para conferir, abra **Configurar › Plugins**: o *Asset Terms* deve aparecer como **Habilitado**. No GLPI 11, também dá para conferir pelo terminal:

```bash
cd /var/www/glpi && sudo -u www-data php bin/console plugin:list | grep -i assetterms
```

### Atualizar para uma versão nova

Rode **os mesmos comandos da instalação**. Eles trocam os arquivos, e o `plugin:install` aplica as mudanças no banco. Nada se perde:
- os termos já arquivados ficam na aba Documentos;
- os termos que aguardam assinatura continuam pendentes.

### Remover

```bash
cd /var/www/glpi
sudo -u www-data php bin/console plugin:deactivate assetterms
sudo -u www-data php bin/console plugin:uninstall assetterms
sudo rm -rf /var/www/glpi/plugins/assetterms
```

Os PDFs dos termos assinados continuam na aba **Documentos** dos computadores. Só os termos que ainda aguardavam assinatura são descartados.

No GLPI 11, o plugin ainda aparece em **Configurar › Plugins** como *Erro / para limpar*. Clique em **Limpar** para tirá-lo da lista.

### Pela tela

1. Baixe o arquivo `assetterms-<versão>.zip` da [última Release](https://github.com/GustavoMS0/GLPI-AssetTerms/releases/latest).
2. Extraia na pasta `plugins` do GLPI. O resultado deve ser `plugins/assetterms/setup.php`.
3. Em **Configurar › Plugins**, clique em **Instalar** e depois em **Ativar** no *Asset Terms*.

### URL do GLPI

O link de assinatura usa o endereço configurado em **Configurar › Geral › URL da aplicação**. Esse endereço precisa abrir na máquina dos colaboradores, por exemplo `http://glpi.empresa.local`. O Install-Gm já configura.

### E-mail (opcional)

**Sem e-mail configurado, o plugin funciona normalmente:**
- o botão **Enviar por e-mail** não aparece;
- o técnico usa **Gerar link de assinatura** e manda o link por outro meio;
- o colaborador não recebe cópia automática do PDF. A TI pode mandar o arquivo da aba Documentos.

Para ativar o e-mail:
- **Servidor de e-mail e remetente:** em **Configurar › Notificações**:
  - ative **Notificações por e-mail**;
  - preencha a **Configuração das notificações por e-mail** e confira com o botão de teste da tela.
- **E-mail de cada colaborador:** em **Administração › Usuários**.

### Status "Em uso" e "Em estoque"

O plugin sugere esses status ao gerar o termo. Se eles não existirem, crie-os em **Configurar › Listas suspensas › Status dos itens**. O Install-Gm já cria os dois e mais os de ciclo de vida:
- Em preparação;
- Em manutenção;
- Empréstimo;
- Desativado / Descarte.

---

## Como usar

1. Abra **Ativos › Termos de Responsabilidade** e escolha o computador. Também dá para abrir o computador e clicar na aba **Termo de Responsabilidade**.
2. Escolha **Entrega ao colaborador**, **Devolução à TI** ou **Somente ciclo de vida**.

### Entrega ou devolução

1. Selecione o **colaborador** e marque os **acessórios**.
2. Confira os **dados do equipamento**. Quando falta algo no inventário, clique no campo para escolher um valor já cadastrado no GLPI ou digite.
3. Se precisar, escreva as **observações**.
4. Escolha como assinar:

| Botão | Quando usar | O que acontece |
|---|---|---|
| **Gerar e arquivar termo** | o colaborador está na sua frente | ele assina no quadro, e o PDF é arquivado na hora |
| **Enviar por e-mail para assinatura** | o colaborador está longe, e o GLPI envia e-mails | ele recebe o link por e-mail, entra no GLPI e assina |
| **Gerar link de assinatura** | o colaborador está longe, ou o GLPI não envia e-mails | você copia o link e manda pelo Teams, WhatsApp ou chat |
| **PDF para assinar no papel** | assinatura física | o PDF abre para imprimir. Depois, anexe a via digitalizada na aba **Documentos** |

Na assinatura pelo link, por e-mail ou copiado, o colaborador:
1. abre o link e entra no GLPI com o próprio usuário. Se já estiver logado, a página abre direto;
2. lê o termo, marca **"Li o termo e concordo"** e assina com o dedo, uma caneta ou o mouse.

Ao assinar:
- o PDF é arquivado na aba **Documentos**;
- o usuário e o status do equipamento são atualizados;
- com o e-mail configurado, o colaborador recebe uma cópia em PDF e quem enviou recebe um aviso.

Enquanto não é assinado, o termo aparece em **Aguardando assinatura**, na aba e no menu, com os botões:
- **Copiar link:** para mandar de novo por qualquer meio;
- **Reenviar:** por e-mail, para o e-mail atual do colaborador;
- **Cancelar:** o link deixa de funcionar.

| Situação | O que acontece ao abrir o link |
|---|---|
| Sem login | vai para a tela de login do GLPI e, depois de entrar, volta para o termo |
| Logado com outro usuário | "Termo não disponível": não mostra nem permite assinar |
| Termo já assinado | mostra a data da assinatura |
| Termo cancelado pela TI | avisa que foi cancelado |

### Somente ciclo de vida

Para mudar o status sem termo nenhum: equipamento para a manutenção, empréstimo, descarte, volta ao estoque etc.

1. Escolha o **novo status**. É obrigatório.
2. Escolha o que acontece com o usuário do equipamento:
   - **Manter:** continua com quem está;
   - **Mover para outra pessoa:** escolha o **novo usuário do GLPI**, digite um **nome de preferência**, ou os dois. O nome fica no campo **Usuário alternativo** do computador. Ele serve para quem não tem usuário no GLPI (terceirizado, estagiário, setor) ou para um apelido;
   - **Remover:** o equipamento fica sem ninguém (limpa também o nome).
3. Escreva o **motivo**, se quiser. Ele vai para o histórico.
4. Clique em **Atualizar status**.

Nenhum PDF é gerado. O histórico do computador registra, por exemplo:

> *Ciclo de vida: status alterado para Em manutenção e usuário removido, sem termo. Observação: Tela quebrada, enviado para a garantia.*
>
> *Ciclo de vida: status alterado para Empréstimo e equipamento movido para João Silva - Terceirizado (sem usuário no GLPI), sem termo.*

### O que vai no PDF

- o cabeçalho, com a empresa, a data e o código do documento;
- o colaborador, com o nome, a matrícula e o e-mail;
- o equipamento, com os dados conferidos pelo técnico;
- os acessórios e as observações;
- a declaração;
- as assinaturas do colaborador e da TI, com a cidade e a data por extenso.

O nome da empresa, o CNPJ, a cidade e o texto vêm da **configuração** (veja abaixo). Sem configuração, o plugin usa o nome e a cidade da entidade do computador e o texto padrão.

---

## Configuração: empresa e texto do termo

Em **Configurar › Plugins**, clique em **Asset Terms**. Administradores também chegam pelo botão **Configurar empresa e texto**, em Ativos › Termos de Responsabilidade.

1. **Escolha a empresa (entidade).**
   - Matriz e filiais podem ter configurações diferentes.
   - Uma filial sem configuração própria usa a da entidade acima. A tela avisa de qual entidade ela está herdando.
2. **Empresa:**
   - **Nome da empresa:** vai no cabeçalho do PDF e no lugar de `{empresa}` no texto;
   - **CNPJ:** opcional, também vai no cabeçalho e no lugar de `{cnpj}`;
   - **Cidade:** vai em "Cidade, 5 de outubro de 2026." acima das assinaturas.
3. **Texto do termo de entrega e do termo de devolução:**
   - título;
   - declaração;
   - compromissos, um por linha (na entrega saem numerados I, II, III...);
   - parágrafo final.
4. Clique em **Ver PDF de exemplo** para conferir antes de salvar, e depois em **Salvar**.

O plugin já vem com o [texto padrão](#texto-padrão-do-termo). Para voltar a ele, use um dos botões:
- **Preencher com o texto padrão:** troca só o texto do formulário, e você ainda salva;
- **Restaurar padrão:** apaga a configuração desta entidade, que volta a herdar da entidade acima ou o padrão.

> **O que muda ao salvar:** só os próximos termos.
> - Os termos já assinados não mudam.
> - Os termos que aguardam assinatura mantêm o texto com que foram enviados: o colaborador assina exatamente o que recebeu.

---

## Texto padrão do termo

É o texto que o plugin traz. Dá para mudar tudo na [tela de configuração](#configuração-empresa-e-texto-do-termo). `{empresa}` é trocado pelo nome da empresa.

### Entrega

> Declaro que recebi da empresa **{empresa}**, em regime de comodato e para uso exclusivo no exercício das minhas atividades profissionais, o equipamento e os acessórios descritos neste termo, em perfeito estado de conservação e funcionamento, ressalvadas as observações registradas. Comprometo-me a:
>
> I. utilizá-lo somente para fins profissionais, conforme a Política de Segurança da Informação e as normas internas;
> II. zelar pela sua guarda e conservação, sem emprestá-lo, cedê-lo ou permitir o uso por pessoas não autorizadas;
> III. não instalar programas não autorizados nem alterar as configurações de segurança, os componentes ou a etiqueta de patrimônio;
> IV. comunicar imediatamente à TI qualquer defeito, dano, perda, furto ou roubo, apresentando boletim de ocorrência nos casos de furto ou roubo;
> V. devolvê-lo com os acessórios, nas mesmas condições em que o recebi, ressalvado o desgaste natural pelo uso normal, sempre que solicitado, na sua substituição ou no encerramento do meu vínculo com a empresa.
>
> Estou ciente de que o equipamento e os dados corporativos nele armazenados pertencem à empresa, podendo ser acessados, monitorados ou removidos conforme a Política de Segurança da Informação e a Lei Geral de Proteção de Dados (Lei nº 13.709/2018). Em caso de dano causado por dolo ou culpa (negligência, imprudência ou imperícia), perda ou extravio, autorizo o desconto do valor correspondente, nos termos do art. 462, § 1º, da Consolidação das Leis do Trabalho (CLT).

### Devolução

> Declaro que, nesta data, devolvi à empresa **{empresa}** o equipamento e os acessórios descritos neste termo, conferidos na presença do(a) responsável pela TI.
>
> - O estado do equipamento e de cada acessório na devolução é o registrado no campo de observações deste termo.
> - Acessórios não listados como devolvidos foram considerados ausentes na conferência.
> - Removi ou entreguei à TI os arquivos pessoais que mantinha no equipamento, quando havia.
>
> Estou ciente de que os dados armazenados no equipamento poderão ser apagados para a sua reutilização, conforme a Política de Segurança da Informação e a Lei Geral de Proteção de Dados (Lei nº 13.709/2018), e de que danos ou ausências registrados neste termo serão tratados conforme o termo de entrega e as normas internas.

### Antes de usar na sua empresa

- **Revisão jurídica:** o texto padrão é um modelo. Peça ao RH ou ao jurídico para revisá-lo, principalmente a autorização de desconto, e ajuste na tela de configuração.
  - O art. 462, § 1º, da CLT só permite descontar o prejuízo do salário em dois casos: quando houve **dolo** do empregado, ou quando o desconto foi **combinado** com ele, o que o termo faz para os casos de culpa.
- **Assinatura na tela ou pelo link:** é uma **assinatura eletrônica simples**, não uma assinatura digital com certificado ICP-Brasil. Pelo link, ela fica mais forte como prova, porque o colaborador entra com o próprio usuário e senha e o PDF registra o usuário, o IP e a data. Se a empresa exigir certificado digital, gere o PDF para assinatura e assine com a ferramenta de certificado da empresa.

---

## Personalizar no código

O texto e os dados da empresa se mudam pela [tela de configuração](#configuração-empresa-e-texto-do-termo). O resto fica em [`assetterms/inc/term.class.php`](assetterms/inc/term.class.php):

| O quê | Onde |
|---|---|
| Texto padrão (o que vem com o plugin) | `defaultTexts()` em [`inc/config.class.php`](assetterms/inc/config.class.php) |
| Lista de acessórios | constante `CHECKLIST` |
| Campos de dados do equipamento | constante `EQUIP_FIELDS` e função `equipmentOptions()` (sugestões) |
| Acessórios marcados por padrão | constante `CHECKLIST_DEFAULT` |
| Layout do PDF | função `buildPdf()` |

---

## Permissões

| Perfil | Vê a aba e o menu | Gera, envia e cancela termos e muda o status |
|---|---|---|
| Pode **ver** computadores | sim, só as listas de termos | não |
| Pode **alterar** o computador | sim | sim |
| **Qualquer usuário**, inclusive do autoatendimento | não | assina pelo link só os termos enviados para ele |

A **tela de configuração** (empresa e texto) é só para quem pode **alterar a configuração do GLPI** (Configurar › Geral), normalmente o Super-Admin.

O PDF usa o tipo de documento **PDF**, que já vem liberado no GLPI. Ele fica em **Configurar › Listas suspensas › Tipos de documento**.

---

## Testes

Validado no GLPI 11.0.10 e no GLPI 10.0.28, com o navegador:

| Cenário | Resultado |
|---|---|
| Abrir a aba de um computador inventariado | formulário com os dados do inventário ✅ |
| Entrega assinada na tela | PDF arquivado e vinculado, usuário vinculado, status **Em uso** ✅ |
| PDF arquivado | abre no navegador, em uma página, com a assinatura e o registro dela ✅ |
| Trocar para devolução | o texto, os rótulos e o status sugerido (**Em estoque**) mudam ✅ |
| Devolução sem assinatura | pede confirmação, arquiva, desvincula o usuário e põe **Em estoque** ✅ |
| PDF para assinar no papel | gerado sem gravar nada ✅ |
| Envio sem token CSRF ou com assinatura que não é PNG | recusado ✅ |
| Nome com acento e apóstrofo (ex.: Maria D'Ávila) | correto na tela, no PDF e no histórico ✅ |
| Celular (390 px) | aba e área de assinatura utilizáveis ✅ |
| Envio por e-mail | e-mail para o colaborador com o link; equipamento só muda depois da assinatura ✅ |
| Link aberto sem login | tela de login do GLPI e, depois de entrar, volta para o termo ✅ |
| Link aberto por outro usuário logado | não mostra o termo; assinar pela API dá erro 403 ✅ |
| Assinar sem "li e concordo" ou sem desenhar | bloqueado ✅ |
| Colaborador do autoatendimento assina pelo link | PDF arquivado, usuário e status atualizados, cópia em PDF para o colaborador e aviso para o técnico ✅ |
| Abrir o link de novo depois de assinar | "Termo já assinado", sem permitir uma segunda assinatura ✅ |
| Reenviar e cancelar | reenvia o mesmo link; depois de cancelar, o link não permite assinar ✅ |
| Colaborador sem e-mail cadastrado | avisa e não envia ✅ |
| GLPI sem e-mail configurado | botão de e-mail some; "Gerar link" cria o termo e mostra o link para copiar; nenhum e-mail enviado ✅ |
| Colaborador assina pelo link copiado | PDF arquivado e equipamento atualizado, sem precisar de e-mail ✅ |
| Somente ciclo de vida | esconde o termo e a assinatura, exige o novo status, muda o status, remove ou mantém o usuário, não gera documento e registra a observação no histórico ✅ |
| Dados do equipamento | sugestões com valores do GLPI; valores editados vão para o termo sem alterar o cadastro do computador ✅ |
| Menu Ativos › Termos de Responsabilidade | aparece no menu; escolher o computador abre o formulário; lista os pendentes de todos os computadores ✅ |
| Usuário do autoatendimento abre a página do menu | acesso negado ✅ |
| Mover para outra pessoa: usuário do GLPI + nome | vincula o usuário e guarda o nome em Usuário alternativo ✅ |
| Mover para outra pessoa: só o nome | equipamento sem usuário do GLPI, com o nome; a tela mostra "Nome (sem usuário no GLPI)" ✅ |
| Remover usuário | limpa o usuário e o nome ✅ |
| Configuração: salvar empresa, CNPJ, cidade e texto | aparecem na tela do termo e no PDF, com `{empresa}` e `{cnpj}` trocados ✅ |
| PDF de exemplo | gerado com o texto do formulário, sem salvar ✅ |
| Texto alterado depois do envio | o colaborador assina o texto do momento do envio ✅ |
| Filial sem configuração | usa a da matriz; com configuração própria, usa a dela ✅ |
| Restaurar padrão | volta ao texto do plugin ✅ |
| Usuário sem permissão de configuração | não acessa a tela ✅ |
| Apóstrofo e `< > &` em textos e nomes (GLPI 10 e 11) | gravados e impressos como digitados ✅ |

---

## Licença

[GPLv3 ou posterior](LICENSE), a mesma do GLPI.
