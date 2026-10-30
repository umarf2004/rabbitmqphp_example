#!/usr/bin/env python3
"""Consume IT490 email jobs from VM4 RabbitMQ and submit via SMTP on VM3."""
import json
import logging
import os
import smtplib
import ssl
import time
from email.message import EmailMessage
from email.utils import parseaddr

import pika

logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s %(message)s')

QUEUE = 'email_delivery_requests'
EXCHANGE = 'test'
ROUTING_KEY = 'notification.email.request'


def send_email(job):
    if not isinstance(job, dict) or job.get('type') != 'deliver_email':
        raise ValueError('Unexpected job type')
    recipient = str(job.get('recipient', ''))
    if not recipient or '\n' in recipient or '\r' in recipient or parseaddr(recipient)[1] != recipient:
        raise ValueError('Invalid recipient email')
    subject = str(job.get('subject', ''))
    body = str(job.get('body', ''))
    if not subject or len(subject) > 200 or '\n' in subject or '\r' in subject:
        raise ValueError('Invalid subject')
    if not body or len(body) > 5000:
        raise ValueError('Invalid email body')

    host = os.environ['SMTP_HOST']
    port = int(os.environ.get('SMTP_PORT', '587'))
    sender = os.environ['SMTP_FROM']
    username = os.environ.get('SMTP_USERNAME', '')
    password = os.environ.get('SMTP_PASSWORD', '')
    use_ssl = os.environ.get('SMTP_SSL', '0') == '1'

    message = EmailMessage()
    message['From'] = sender
    message['To'] = recipient
    message['Subject'] = subject
    message.set_content(body)
    if use_ssl:
        with smtplib.SMTP_SSL(host, port, timeout=20, context=ssl.create_default_context()) as smtp:
            if username:
                smtp.login(username, password)
            smtp.send_message(message)
    else:
        with smtplib.SMTP(host, port, timeout=20) as smtp:
            smtp.ehlo()
            smtp.starttls(context=ssl.create_default_context())
            smtp.ehlo()
            if username:
                smtp.login(username, password)
            smtp.send_message(message)


def run():
    params = pika.URLParameters(os.environ['AMQP_URL'])
    params.heartbeat = 60
    params.blocked_connection_timeout = 30
    connection = pika.BlockingConnection(params)
    channel = connection.channel()
    # VM4 owns queue creation; passive check detects a missing queue.
    channel.queue_declare(queue=QUEUE, passive=True)
    channel.basic_qos(prefetch_count=1)

    def handle(ch, method, properties, body):
        try:
            job = json.loads(body)
            send_email(job)
            # SMTP accepted the message, not a guarantee it reached the inbox.
            logging.info('SMTP accepted email event_id=%s', job.get('event_id'))
            ch.basic_ack(delivery_tag=method.delivery_tag)
        except Exception as error:
            logging.exception('Email job failed: %s', error)
            # Avoid endless redelivery loops. Consider a dead-letter queue/retries later.
            ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

    channel.basic_consume(queue=QUEUE, on_message_callback=handle, auto_ack=False)
    logging.info('Waiting on RabbitMQ queue: %s', QUEUE)
    channel.start_consuming()


if __name__ == '__main__':
    while True:
        try:
            run()
        except KeyboardInterrupt:
            break
        except Exception as error:
            logging.exception('Worker disconnected: %s', error)
            time.sleep(5)
