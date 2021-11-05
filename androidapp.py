from kivy.lang import Builder

from kivymd.app import MDApp
from kivy.uix.button import Button
from kivy.properties import StringProperty
import requests
import json
from kivy.utils import get_color_from_hex
from kivymd.uix.card import MDCard
from kivy.uix.boxlayout import BoxLayout
from kivy.uix.label import Label
kv=            '''
#:import get_color_from_hex kivy.utils.get_color_from_hex



MDScreen:

    MDBottomNavigation:
        panel_color: get_color_from_hex("#eeeaea")
        selected_color_background: get_color_from_hex("#97ecf8")
        text_color_active: 0, 0, 0, 1

        MDBottomNavigationItem:
            name: 'screen 1'
            text: 'Orari'
            icon: 'train'
            badge_icon: "numeric-10"
            GridLayout:
                # this layout is populated with Button widgets in app code
                id: entries_box
                cols: 1
                rows: 20
            
                Button:
                    text: "Create Widgets"
                    on_release: app.create_widgets()

                Label:
                    text: "Create Widgetstestedrrrrr"
                    color: 0,0,0,0

        MDBottomNavigationItem:
            name: 'screen 2'
            text: 'Notizie'
            icon: 'newspaper'
            badge_icon: "numeric-5"

            MDLabel:
                text: 'Discord'
                halign: 'center'

        MDBottomNavigationItem:
            name: 'screen 3'
            text: 'Impostazioni'
            icon: 'cog-outline'

            MDLabel:
                text: 'LinkedIN'
                halign: 'center'
'''
print("Prova delle API della GTT")

print("Inserisci il numero della fermata:")
fermata = "2604"

response = requests.get('http://gpa.madbob.org/query.php?stop=' + fermata)
json_data = response.json()


for n in range(len(json_data)):
        print('Name: ', json_data[n]['line'])
        print('Orario: ', json_data[n]['hour'])
        print('Previsioni in tempo reale: ', json_data[n]['realtime'])

class Test(MDApp):
    def __init__(self, **kwargs):
        """Construct main app."""
        super().__init__(**kwargs)
        self.name_to_phone = {"Bob Brown": "0414144411", "Cat Cyan": "0441411211", "Oren Ochre": "0432123456"}
    def create_widgets(self):
        """Create buttons from dictionary entries and add them to the GUI."""
        for n in range(len(json_data)):
            # create a button for each data entry, specifying the text and id
            # (although text and id are the same in this case, you should see how this works)
            temp_button = Button(text="Linea " + json_data[n]['line'] + "                                           Ora: " + json_data[n]['hour'],
                                 halign="left",
                                 color=(98, 0, 238, 1),
                                 background_color=get_color_from_hex("#FFD8E4"),
                                 background_normal= 'normal.png',
                                 border=(0,20,0,20))
            # add the button to the "entries_box" layout widget
            self.root.ids.entries_box.add_widget(temp_button)
    def build(self):
        self.theme_cls.material_style = "M3"
        self.root = Builder.load_string(kv)
        return self.root

        

Test().run()
