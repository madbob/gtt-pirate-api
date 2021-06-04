import requests
import json

print("Prova delle API della GTT")

print("Inserisci il numero della fermata:")
fermata = input()

response = requests.get('http://gpa.madbob.org/query.php?stop=' + fermata)
json_data = response.json()


for n in range(len(json_data)):
        print('Name: ', json_data[n]['line'])
        print('Orario: ', json_data[n]['hour'])
        print('Previsioni in tempo reale: ', json_data[n]['realtime'])
        
