[![Build Status](https://travis-ci.org/dpeuscher/ynab_automation.svg?branch=master)](https://travis-ci.org/dpeuscher/ynab_automation)
[![Violinist enabled](https://img.shields.io/badge/violinist-enabled-brightgreen.svg)](https://violinist.io)

# YNAB Automation

A cli tool to import hbci and paypal transactions into your ynab (www.youneedabudget.com) account.

## Configuration
Setup an .env file with your data

The _X parameters are either _1 or _2 which can be used for 2 different bank accounts. Currently only two are supported.

##### YNAB_TOKEN
Generate your _Personal Access Token_ as described here: https://api.youneedabudget.com/#personal-access-tokens

##### FHP_
You should ask your bank for your FinTS / HBCI Server data and insert it. As a shortcut, you can find a list of some
banks servers here: https://github.com/hbci4j/hbci4java/blob/master/src/main/resources/blz.properties

Port will in general be 443 (since the URL should be https),

**FHP_BANK_URL_X** => HBCI-URL (see link)

**FHP_BANK_PORT_X** => Should be 443 - otherwise ask your bank or search the internet

**FHP_BANK_CODE_X** => Your bank code (german: bankleitzahl).

**FHP_ONLINE_BANKING_USERNAME_X** => Username for your online banking login. Might be your account number. Sometimes 
requires a user-id which could be 001. If your bank account number is 12345678 this might be your login: 12345678001. 
Or 12345678. Or a self-defined name. Ask your bank.
 
**FHP_ONLINE_BANKING_PIN_X** => Your online banking pin. This is probably 5 characters long since banks think this is
secure enough.

##### YNAB_BUDGET_X, YNAB_ACCOUNT_NAME_X
The YNAB_BUDGET_X is an id that ynab will generate for you. Log into your ynab account 
(https://app.youneedabudget.com/users/login), wait for the page loading and copy the URL. It will look like this:
app.youneedabudget.com/**12345678-90ab-cdef-0123-4567890abcde**/budget . In this url, this is your YNAB_BUDGET_X: 
2345678-90ab-cdef-0123-4567890abcde

YNAB_ACCOUNT_NAME_X is the name of your account, that you gave. Probably the name of your bank like "Sparkasse".

##### IFTTT_WEBHOOK_NAME, IFTTT_WEBHOOK_KEY
Create a IFTTT-Webhook here: https://ifttt.com/create/if-receive-a-web-request?sid=2
Add a notification to get new transactions pushed to your phone. Value1 is the issuer, Value2 the amount, Value3 the 
description.

Go to this page to get your IFTTT_WEBHOOK_KEY: https://ifttt.com/services/maker_webhooks/settings

The IFTTT_WEBHOOK_NAME is the trigger name you give your webhook when setup. 

##### PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET
Create a PayPal developer account here https://developer.paypal.com and setup a new app. You should get a clientId and a
clientSecret. Put the in there.

## Import
The import tries to find the best matching transactions in your ynab account and only add new transactions that were not
there yet. It also takes into account the scheduled transactions as good as possible. If you want a full coverage of 
transactions, setup a cronjob to let it run every hour or so.

##### Commands

Run the following script to import data from your HBCI bank account into your ynab budget account:
```bash
bin/console --bankNumber=X hbci:retrieve P30D
```
X is either 1 or 2 (depending which bank configuration should be used). if omitted it defaults to 1.

P30D is a DateTimeInterval string from PHP, which defines how far into the past you want to look for new transactions.
P30D is for 30 days. See http://php.net/manual/de/dateinterval.construct.php for details.

For importing PayPal use this command:
```bash
bin/console paypal:retrieve P30D
```
This only supports 1 PayPal account. PayPal also does only allow 31 Days of retrieval and since I did not implement
pagination, it allows only for 100 transactions at a time. Should be enough for most usages, if you need more
transactions, lower the run intervals.

## Push transactions
Additional to the import into ynab function, I added a push of transactions via ifttt. It will keep a database of
transactions and only pushes new ones (identified by a checksum). Feel free to read the database yourself in
var/data/hbci.db

##### Commands
To push your bank transactions use this command:
```bash
bin/console --bankNumber=X hbci:push P31D
```
and for PayPal use this command:
```bash
bin/console paypal:push P31D
```
See section _Import_ to understand the parameters.

