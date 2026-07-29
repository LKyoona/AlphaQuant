# -*- coding: utf-8 -*-
import os
import time
import random
import requests
import sys

# reload(sys)
# sys.setdefaultencoding('utf8')


def req(url):
    print(url)
    try:
        response = requests.get(url)
        print(response.text)
    except requests.RequestException as e:
        print(e)


def reqall():
    host = "https:/www.bszjelr.cn"
    member = [
        '/api/home/Cron/index',
        '/api/wallet/rpc_transfer/index'
    ]
    for each in member:
        req(host+each)


while True:
    reqall()
    time.sleep(1)
