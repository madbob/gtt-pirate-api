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
from kivymd.uix.behaviors import RoundedRectangularElevationBehavior
from kivymd.uix.card import MDCard
import sys
class content(Screen):
    pass
kv=            '''
#:import get_color_from_hex kivy.utils.get_color_from_hex
#: import ScreenManager kivy.uix.screenmanager.ScreenManager
#: import Screen kivy.uix.screenmanager.ScreenManager

<MD3Card>
    padding: 16


    MDRelativeLayout:
        size_hint: None, None
        size: root.size


        MDLabel:
            id: label
            text: root.text
            adaptive_size: True
            color: .2, .2, .2, .8

<content>
    orientation: "vertical"
    spacing: "12dp"
    size_hint_y: None
    height: "50dp"

    MDTextField:
        id:name
        hint_text: "Numero della fermata"
    

ScreenManager:
    Screen:
        name: "menu"
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

                    padding: [0, 125, 0, 0]

                        
                MDFloatingActionButton:
                    icon: "magnify"
                    type: "type_button"
                    on_release: app.show_alert_dialog()
                    pos_hint:{"center_x":.85,"y":0.07}

            MDBottomNavigationItem:
                name: 'screen 2'
                text: 'Mappa'
                icon: 'map'
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

        MDToolbar
            id: bar
            title: "Orari GTT"
            md_bg_color: 0, 0.5, 0.5, 1
            elevation: 12
            pos_hint: {"top":1}



'''
class Content(Screen):
    pass

class MD3Card(MDCard, RoundedRectangularElevationBehavior):
    '''Implements a material design v3 card.'''

    text = StringProperty()

class Test(MDApp):
    dialog = None
    def __init__(self, **kwargs):
        """Construct main app."""
        super().__init__(**kwargs)


    def clear_all(self):
        """Clear all of the widgets that are children of the "entries_box" layout widget."""
        self.root.ids.entries_box.clear_widgets()

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
        self.root.ids.entries_box.clear_widgets()
        fermata =  self.dialog.content_cls.ids.name.text

    
        response = requests.get('http://gpa.madbob.org/query.php?stop=' + fermata)
        json_data = response.json()
        if response.text=="[]":
            self.root.ids.entries_box.add_widget(
                MD3Card(
                    line_color=(0.2, 0.2, 0.2, 0.8),
                    text="Linea inesistente",
                    md_bg_color=get_color_from_hex("#f8f5f4"),
                )
            )
            return
        style="filled"

        for n in range(len(json_data)):
            realtime=""
            if json_data[n]['realtime']=="true":
                realtime="*"
            self.root.ids.entries_box.add_widget(
                MD3Card(
                    line_color=(0.2, 0.2, 0.2, 0.8),
                    text="Linea " + json_data[n]['line'] + "                                           Ora: " + json_data[n]['hour']+ realtime,
                    md_bg_color=get_color_from_hex("#f8f5f4"),
                )
            )

    def on_start(self):
        styles = {
            "elevated": "#f6eeee", "filled": "#f4dedc", "outlined": "#f8f5f4"
        }

    def build(self):
        self.theme_cls.material_style = "M3"
        self.root = Builder.load_string(kv)
        return self.root

        

Test().run()
