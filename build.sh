p4a clean_builds 
p4a clean_dists
p4a apk --private . --package=org.gtt --name "GTT" --version 0.5 --bootstrap=sdl2 --requirements=python3,requests,chardet,charset-normalizer,idna,kivy==2.0.0,kivymd==0.104.2,pillow,urllib3,openssl,sdl2_ttf==2.0.15 --sdk-dir $HOME/Android/Sdk --ndk-dir $HOME/Android/android-ndk-r19c --android-api 31 --ndk-api 21 --permission INTERNET
