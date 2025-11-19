# CSV Discount Import

## Repository Setup
1. Assicurati di avere PHP/composer installati e lancia `composer install --no-dev` per generare la cartella `vendor/` in locale (verrà ignorata sul repo grazie al nuovo `.gitignore`).
2. Inizializza il repository se necessario: `git init && git branch -m main`.
3. Configura nome/email: `git config user.name "Tuo Nome"` e `git config user.email "you@example.com"`.
4. Aggiungi tutti i file rilevanti: `git add -A` e crea il primo commit `git commit -m "feat: inizializza modulo sconti"`.
5. Crea un repository vuoto su GitHub e collega il remote: `git remote add origin git@github.com:ORG/ktk_discount_import.git`.
6. Pubblica il codice con `git push -u origin main`.

> Suggerimento: i file generati (log, csv di prova, vendor) non saranno caricati perché esclusi; se devi allegare un csv di esempio rinomina il file con estensione diversa o rimuovi temporaneamente la voce dal `.gitignore`.

MODULE CONFIGURATION:
Module has now little customization that can be accessed directly from BO Module page:

Allow Discount Import → this is a security configuration, if set to no all the import call will be blocked without even starting

Api Key → this is another security configuration, if set this key must be passed as a query string parameter with name “api_key” when calling the GET endpoint

Filepath → indicates where to get the file to be imported, this is a relative path so the root of the website will be always use as prefix of the inserted path

Track output result in CSV → if set to yes, a full output CSV will be written as a log of the operation, line by line

Send Mail with result → wheter to send a mail with number of output status (ok, ko, total) and if written, will attach also the output csv file

Result Mail Recipients → who will receives previous email


MODULE USAGE:

to run the cronjob, simply call the url {site-base-url}/ktk-import-discount/process-csv indicating a query string parameters with key “api_key” if requested by configuration (in our example will be https://staging.profumeriecalcagni.com/ktk-import-discount/process-csv?api_key=08487082-2607-4f38-bdef-4a13ae2db466)

MODULE FLOW:

GET Endpoint Request received

authorization check

import allowed check

module configuration check

expired specific price cancellation

validate csv file (exists, is csv, empty, headers)

copy input file inside module log folder for history

import data row by row

check row validity

search productid or combinationid by reference

remove previous matching specific price for specific product/combination

insert new specific price

send result mail

delete input file

A specific execution id (example IMPORTCONTROLLER_GET_1_20220114125051) will be generated for each call, this can be used to track down the behaviour of the module execution inside log and inside folder that contains input and eventually output csv. Also the result mail will use this id to send information about output.


CHANGE LOG


2023.03.08 

The main csv is now splitted in several files at the first step
In the next step each csv is processed separately.


added KTK_DISCOUNT_IMPORT_CSV_ROW_TO_SPLIT: number of row for splitting csv. if changed you should remove filed in KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT
added KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT: file path of splitted files (e.g /sap_splitted/)
addded KTK_DISCOUNT_IMPORT_CSV_FILE_PATH_NUM: file path to process (default: NO_PATH)

they shoud be added foreache microsite


2023.03.17

Before splitting csv on the main Shop (1) all specific prices are deleted


2023.05.29

this url should be call once a day

XXXURL/ktk-import-discount/process-csv?api_key=08487082-2607-4f38-bdef-4a13ae2db466&do_split=1

this url should be several times a day
XXXURL/memmi.kotukodev.it/ktk-import-discount/process-csv?api_key=08487082-2607-4f38-bdef-4a13ae2db466
