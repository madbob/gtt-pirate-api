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
from kivymd.uix.button import MDFlatButton
from kivymd.uix.dialog import MDDialog
from kivymd.uix.textfield import MDTextField
from kivy.uix.screenmanager import ScreenManager, Screen, FadeTransition
class content(Screen):
    pass
kv=            '''
#:import get_color_from_hex kivy.utils.get_color_from_hex
<content>
    name: "content"
    id: content
    orientation: "vertical"
    spacing: "12dp"
    size_hint_y: None
    height: "50dp"
    GridLayout:
        name: "content"
        id: content
        MDTextField:
            id:name
            hint_text: "Numero della fermata"
    

MDScreen:
    MDBottomNavigation:
        panel_color: get_color_from_hex("#eeeaea")
        selected_color_background: get_color_from_hex("#97ecf8")
        text_color_active: 0, 0, 0, 1
        MDBottomNavigationItem:
            name: 'content'
            text: 'Orari'
            icon: 'train'
            badge_icon: "numeric-10"
            GridLayout:
                # this layout is populated with Button widgets in app code
                id: entries_box
                cols: 1
                rows: 20
                    
            MDFloatingActionButton:
                icon: "magnify"
                type: "type_button"
                on_release: app.show_alert_dialog()
                pos_hint:{"center_x":.9,"y":0.07}

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
class Content(Screen):
    pass


class Test(MDApp):
    dialog = None
    def __init__(self, **kwargs):
        """Construct main app."""
        super().__init__(**kwargs)
        self.name_to_phone = {"Bob Brown": "0414144411", "Cat Cyan": "0441411211", "Oren Ochre": "0432123456"}

    
    def show_alert_dialog(self):
        if not self.dialog:
            self.dialog = MDDialog(
                title="Inserire il numero della fermata",
                type="custom",
                content_cls=Content(),
                buttons=[
                    MDFlatButton(
                        text="OK",
                        theme_text_color="Custom",
                        text_color=self.theme_cls.primary_color,
                        on_release=lambda x:self.create_widgets(),
                        on_press=lambda x:self.dialog.dismiss()
                    ),
                ],
            )
        self.dialog.open()
        


    def create_widgets(self):
        print("Prova delle API della GTT")

        fermata =  self.root.ids.entries_box.ids.name.text


        response = requests.get('http://gpa.madbob.org/query.php?stop=' + fermata)
        json_data = response.json()

        for n in range(len(json_data)):
            temp_button = Button(text="Linea " + json_data[n]['line'] + "                                           Ora: " + json_data[n]['hour'],
                                 halign="left",
                                 background_color=get_color_from_hex("#0000FF"),
                                 border=(0,20,0,20))
            self.root.ids.entries_box.add_widget(temp_button)



    def build(self):
        self.theme_cls.material_style = "M3"
        self.root = Builder.load_string(kv)
        return self.root

        

Test().run()
